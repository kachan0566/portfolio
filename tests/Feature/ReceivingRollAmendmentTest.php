<?php

namespace Tests\Feature;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\ReceivingLine;
use App\Models\ReceivingRollAmendment;
use App\Models\Shipment;
use App\Models\ShipmentRollAllocation;
use App\Services\Receiving\ReceivingLineTotals;
use App\Services\Receiving\RollAmendmentService;
use App\Support\PurchaseOrderType;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingRollAmendmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_show_receiving_line_roll_edit_page(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-002'))
            ->firstOrFail();

        $response = $this->get(route('receiving-lines.show', $line->id));

        $response->assertOk();
        $response->assertSee('反明細修正');
        $response->assertSee('変更履歴');
    }

    public function test_amend_greige_roll_records_history_and_resyncs_line_totals(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-002'))
            ->firstOrFail();

        $roll = GreigeRoll::query()->where('receiving_line_id', $line->id)->orderBy('id')->firstOrFail();
        $originalM = (float) $roll->actual_qty_m;
        $newM = $originalM + 5.0;

        $response = $this->put(route('receiving-lines.update-greige-roll', [$line->id, $roll->id]), [
            'tan_qty' => (float) $roll->tan_qty,
            'actual_qty_m' => $newM,
            'reason' => '再計測',
        ]);

        $response->assertRedirect(route('receiving-lines.show', $line->id));
        $response->assertSessionHas('success');

        $roll->refresh();
        $this->assertSame($newM, (float) $roll->actual_qty_m);

        $line->refresh();
        $expectedM = (int) round(
            (float) GreigeRoll::query()->where('receiving_line_id', $line->id)->sum('actual_qty_m')
        );
        $this->assertSame($expectedM, (int) $line->qty_m);

        $this->assertDatabaseHas('receiving_roll_amendments', [
            'receiving_line_id' => $line->id,
            'roll_id' => $roll->id,
            'field' => ReceivingRollAmendment::FIELD_ACTUAL_QTY_M,
            'reason' => '再計測',
        ]);
    }

    public function test_amend_product_roll_via_service_syncs_po_line_received(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-001'))
            ->firstOrFail();

        $roll = ProductRoll::query()->where('receiving_line_id', $line->id)->orderBy('id')->firstOrFail();
        $poLine = $line->purchaseOrderLine;
        $beforeReceived = (int) ($poLine->received_qty_m ?? 0);

        RollAmendmentService::amendProductRoll(
            $line,
            $roll,
            (float) $roll->tan_qty,
            (float) $roll->actual_qty_m + 3,
            'テスト修正',
        );

        $poLine->refresh();
        $line->refresh();
        $this->assertGreaterThan($beforeReceived, (int) $poLine->received_qty_m);
        $this->assertSame((int) $poLine->received_qty_m, (int) $line->qty_m);
    }

    public function test_shipped_product_roll_cannot_be_amended(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-001'))
            ->firstOrFail();

        $roll = ProductRoll::query()->where('receiving_line_id', $line->id)->firstOrFail();
        $roll->update(['status' => ProductRoll::STATUS_SHIPPED]);

        $response = $this->put(route('receiving-lines.update-product-roll', [$line->id, $roll->id]), [
            'tan_qty' => (float) $roll->tan_qty,
            'actual_qty_m' => (float) $roll->actual_qty_m + 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_allocated_product_roll_cannot_be_amended(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-001'))
            ->firstOrFail();

        $roll = ProductRoll::query()->where('receiving_line_id', $line->id)->firstOrFail();

        $shipment = Shipment::query()->create([
            'code' => 'SH-TEST-001',
            'order_id' => 1,
            'product_id' => $roll->product_id,
            'qty_tan' => 1,
            'qty_m' => 10,
            'shipped_date' => '2026-07-01',
        ]);

        ShipmentRollAllocation::query()->create([
            'shipment_id' => $shipment->id,
            'product_roll_id' => $roll->id,
            'consumed_tan_qty' => 1,
            'consumed_qty_m' => 10,
        ]);

        $reason = RollAmendmentService::productRollEditBlockReason($roll->fresh());
        $this->assertNotNull($reason);
    }

    public function test_consumed_greige_roll_cannot_be_amended(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-002'))
            ->firstOrFail();

        $roll = GreigeRoll::query()->where('receiving_line_id', $line->id)->firstOrFail();
        $roll->update(['status' => GreigeRoll::STATUS_CONSUMED]);

        $reason = RollAmendmentService::greigeRollEditBlockReason($roll->fresh());
        $this->assertNotNull($reason);
    }

    public function test_amendments_index_lists_history(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-002'))
            ->firstOrFail();

        $roll = GreigeRoll::query()->where('receiving_line_id', $line->id)->firstOrFail();

        ReceivingLineTotals::sync($line->fresh());
        RollAmendmentService::amendGreigeRoll($line, $roll, (float) $roll->tan_qty, (float) $roll->actual_qty_m + 2, '履歴テスト');

        $response = $this->get(route('receiving-lines.amendments', $line->id));

        $response->assertOk();
        $response->assertSee('履歴テスト');
        $response->assertSee($roll->code);
    }

    public function test_receiving_index_shows_roll_amend_link_for_fabric_lines(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-002'))
            ->firstOrFail();

        $response = $this->get(route('receivings.index'));

        $response->assertOk();
        $response->assertSee(route('receiving-lines.show', $line->id), false);
        $response->assertSee('反修正');
    }

    public function test_yarn_receiving_line_redirects_from_roll_edit(): void
    {
        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-005'))
            ->firstOrFail();

        $response = $this->get(route('receiving-lines.show', $line->id));

        $response->assertRedirect(route('receivings.index'));
        $response->assertSessionHas('error');
    }
}
