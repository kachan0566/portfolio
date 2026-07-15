<?php

namespace App\Http\Controllers;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\ReceivingLine;
use App\Services\Receiving\RollAmendmentService;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivingLineController extends Controller
{
    public function show(ReceivingLine $line): View|RedirectResponse
    {
        $context = $this->loadContext($line);
        if ($context === null) {
            return redirect()->route('receivings.index')
                ->with('error', 'この入荷明細は反明細の修正対象ではありません。');
        }

        return view('receiving-lines.show', $context);
    }

    public function amendments(ReceivingLine $line): View|RedirectResponse
    {
        $context = $this->loadContext($line);
        if ($context === null) {
            return redirect()->route('receivings.index')
                ->with('error', 'この入荷明細は反明細の修正対象ではありません。');
        }

        $context['amendments'] = $line->rollAmendments()->get();

        return view('receiving-lines.amendments', $context);
    }

    public function updateGreigeRoll(Request $request, ReceivingLine $line, GreigeRoll $roll): RedirectResponse
    {
        return $this->updateRoll($request, $line, $roll, PurchaseOrderType::GREIGE);
    }

    public function updateProductRoll(Request $request, ReceivingLine $line, ProductRoll $roll): RedirectResponse
    {
        return $this->updateRoll($request, $line, $roll, PurchaseOrderType::PRODUCT);
    }

    private function updateRoll(
        Request $request,
        ReceivingLine $line,
        GreigeRoll|ProductRoll $roll,
        string $expectedType,
    ): RedirectResponse {
        $context = $this->loadContext($line);
        if ($context === null || ($context['poType'] ?? '') !== $expectedType) {
            return redirect()->route('receivings.index')
                ->with('error', '無効な入荷明細です。');
        }

        $validated = $request->validate([
            'tan_qty' => ['required', 'numeric', 'gt:0'],
            'actual_qty_m' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [], [
            'tan_qty' => '反数',
            'actual_qty_m' => '実測m',
            'reason' => '修正理由',
        ]);

        $tanQty = QtyHelper::roundReceivingTan((float) $validated['tan_qty']);
        if (! QtyHelper::isValidReceivingTanStep($tanQty)) {
            return back()->withInput()->withErrors([
                'tan_qty' => '反数は 0.25反刻みで入力してください。',
            ]);
        }

        try {
            $result = $roll instanceof GreigeRoll
                ? RollAmendmentService::amendGreigeRoll(
                    $line,
                    $roll,
                    $tanQty,
                    (float) $validated['actual_qty_m'],
                    $validated['reason'] ?? null,
                )
                : RollAmendmentService::amendProductRoll(
                    $line,
                    $roll,
                    $tanQty,
                    (float) $validated['actual_qty_m'],
                    $validated['reason'] ?? null,
                );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('receiving-lines.show', $line->id)
            ->with('success', $result['message']);
    }

    /** @return array<string, mixed>|null */
    private function loadContext(ReceivingLine $line): ?array
    {
        $line->load([
            'receiving',
            'purchaseOrderLine.purchaseOrder.supplier',
            'purchaseOrderLine.greige',
            'purchaseOrderLine.product',
            'greigeRolls',
            'productRolls',
        ]);

        $po = $line->purchaseOrderLine?->purchaseOrder;
        $poType = (string) ($po?->type ?? '');
        if (! in_array($poType, [PurchaseOrderType::GREIGE, PurchaseOrderType::PRODUCT], true)) {
            return null;
        }

        $poLine = $line->purchaseOrderLine;
        $sku = $poType === PurchaseOrderType::GREIGE
            ? ($poLine?->greige?->sku ?? '—')
            : ($poLine?->product?->sku ?? '—');

        $rolls = $poType === PurchaseOrderType::GREIGE
            ? $line->greigeRolls->sortBy('code')->values()
            : $line->productRolls->sortBy('code')->values();

        $rollRows = $rolls->map(function ($roll) use ($poType) {
            $blockReason = $roll instanceof GreigeRoll
                ? RollAmendmentService::greigeRollEditBlockReason($roll)
                : RollAmendmentService::productRollEditBlockReason($roll);

            return [
                'roll' => $roll,
                'editable' => $blockReason === null,
                'block_reason' => $blockReason,
            ];
        });

        return [
            'line' => $line,
            'receiving' => $line->receiving,
            'po' => $po,
            'poType' => $poType,
            'poTypeLabel' => PurchaseOrderType::label($poType),
            'sku' => $sku,
            'rollRows' => $rollRows,
            'amendmentCount' => $line->rollAmendments()->count(),
        ];
    }
}
