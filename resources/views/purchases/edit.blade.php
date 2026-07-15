@extends('layouts.app')

@section('title', '発注編集')
@section('breadcrumb', '取引 / 発注管理 / 編集')

@section('content')
    <div class="page-header">
        <div>
            <h1>発注編集</h1>
            <p class="lead">{{ $purchase->code }}（{{ $purchase->type_label }}）の依頼先・出荷先・状態を編集します。</p>
        </div>
        <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-secondary">
            @include('partials.icon', ['name' => 'back']) 詳細に戻る
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px;">
            <ul style="margin:0;padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card form-card" style="max-width:none;">
        <div class="card__body">
            <form action="{{ route('purchases.update', $purchase->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <div class="field">
                        <label class="label">発注種別</label>
                        <input class="input" type="text" readonly value="{{ $purchase->type_label }}">
                    </div>
                    <div class="field">
                        <label class="label">品番</label>
                        <input class="input code-cell" type="text" readonly value="{{ $purchase->sku }}">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label class="label">数量</label>
                        @php $qtyLabel = view('partials.purchase-qty', ['purchase' => $purchase])->render(); @endphp
                        <input class="input mono" type="text" readonly value="{{ strip_tags($qtyLabel) }}">
                    </div>
                    <div class="field">
                        <label class="label" for="status">状態<span class="req">*</span></label>
                        <select class="select" id="status" name="status" required>
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}" @selected(old('status', $purchase->status) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label class="label" for="supplier_id">依頼先<span class="req">*</span></label>
                        <select class="select" id="supplier_id" name="supplier_id" required>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected((string) old('supplier_id', $purchase->supplier_id ?? '') === (string) $s->id || $s->name === $purchase->supplier)>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="ship_to_id">出荷先<span class="req">*</span></label>
                        <select class="select" id="ship_to_id" name="ship_to_id" required>
                            @foreach ($shipTos as $st)
                                <option value="{{ $st->id }}" @selected((string) old('ship_to_id', $purchase->ship_to_id ?? '') === (string) $st->id || $st->name === $purchase->ship_to)>
                                    {{ $st->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label class="label" for="order_date">発注日<span class="req">*</span></label>
                        <input class="input" type="date" id="order_date" name="order_date"
                               value="{{ old('order_date', $purchase->order_date) }}" required>
                    </div>
                    <div class="field">
                        <label class="label" for="due_date">納期<span class="req">*</span></label>
                        <input class="input" type="date" id="due_date" name="due_date"
                               value="{{ old('due_date', $purchase->eta) }}" required>
                    </div>
                </div>

                @if ($purchase->type === \App\Support\PurchaseOrderType::PRODUCT)
                    <div class="form-row">
                        <div class="field">
                            <label class="label" for="finish_date">入荷予定日</label>
                            <input class="input" type="date" id="finish_date" name="finish_date"
                                   value="{{ old('finish_date', $purchase->finish_date ?? '') }}">
                            <p class="field-hint">月末在庫予想の入荷予定計算に使用します（納期とは別）。</p>
                        </div>
                        <div class="field" style="flex:1;">
                            <label class="label" for="arrival_memo">メモ</label>
                            <textarea class="textarea" id="arrival_memo" name="arrival_memo" rows="3"
                                      placeholder="例）染工場から6/16上がり連絡あり">{{ old('arrival_memo', $purchase->arrival_memo ?? '') }}</textarea>
                        </div>
                    </div>
                @else
                    <div class="field">
                        <label class="label" for="arrival_memo">メモ</label>
                        <textarea class="textarea" id="arrival_memo" name="arrival_memo" rows="3"
                                  placeholder="例）入荷に関するメモ">{{ old('arrival_memo', $purchase->arrival_memo ?? '') }}</textarea>
                        <p class="field-hint">糸・生機発注の入荷予定日は納期と同じです。納期を変更すると入荷予定日も連動します。</p>
                    </div>
                @endif

                @if (count($lineStageRows ?? []) > 1 && in_array($purchase->type, [\App\Support\PurchaseOrderType::GREIGE, \App\Support\PurchaseOrderType::PRODUCT], true))
                    <div class="field">
                        <label class="label">明細行ごとの生産工程</label>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            @foreach ($lineStageRows as $lineRow)
                                <div style="border:1px solid var(--border);border-radius:8px;padding:12px;">
                                    <div class="t-strong" style="margin-bottom:8px;">
                                        行 {{ $lineRow['line_no'] }}: {{ $lineRow['sku'] }}
                                    </div>
                                    @if ($lineRow['stage_editable'] && ($lineStageOptions ?? []) !== [])
                                        <select class="select" name="line_stages[{{ $lineRow['id'] }}]" style="max-width:320px;">
                                            @if ($purchase->type === \App\Support\PurchaseOrderType::GREIGE)
                                                <option value="">（未設定）</option>
                                            @endif
                                            @foreach ($lineStageOptions as $option)
                                                <option value="{{ $option }}" @selected($option === $lineRow['manual_stage'])>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input class="input" type="text" readonly value="{{ $lineRow['current_stage'] }}">
                                        <p class="field-hint">入荷が始まっているため、この行の工程は自動表示のみです。</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="field-hint" style="margin-top:8px;">
                            @if ($purchase->type === \App\Support\PurchaseOrderType::GREIGE)
                                織工場から織編開始の連絡があった行だけ「織編機投入済」を選びます。糸入荷・生機出荷は入荷記録から自動で更新されます。
                            @else
                                染工場への投入連絡があった行だけ「染機投入済」を選びます。在庫中は入荷記録から自動で更新されます。
                            @endif
                        </p>
                    </div>
                @elseif ($manualStageEditable && $manualStageOptions !== [])
                    <div class="field">
                        <label class="label" for="stage">生産工程</label>
                        <select class="select" id="stage" name="stage" style="max-width:320px;">
                            @if ($purchase->type === \App\Support\PurchaseOrderType::GREIGE)
                                <option value="">（未設定）</option>
                            @endif
                            @foreach ($manualStageOptions as $option)
                                <option value="{{ $option }}" @selected($option === $manualStage)>{{ $option }}</option>
                            @endforeach
                        </select>
                        <p class="field-hint">
                            @if ($purchase->type === \App\Support\PurchaseOrderType::GREIGE)
                                織工場から織編開始の連絡があったら「織編機投入済」を選びます。糸入荷・生機出荷は入荷記録から自動で更新されます。
                            @else
                                染工場への投入連絡後に「染機投入済」を選びます。在庫中・出荷済は入荷記録から自動で更新されます。
                            @endif
                        </p>
                    </div>
                @elseif (in_array($purchase->type, [\App\Support\PurchaseOrderType::GREIGE, \App\Support\PurchaseOrderType::PRODUCT], true))
                    <div class="field">
                        <label class="label">生産工程</label>
                        <input class="input" type="text" readonly value="{{ $purchase->stage }}">
                        <p class="field-hint">入荷が始まっているため、工程は自動表示のみです。</p>
                    </div>
                @endif

                <div class="actions">
                    <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'check']) 更新する</button>
                    <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
@endsection
