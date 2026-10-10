@extends('admin.layout')
@section('title', $word ? '编辑单词' : '新建单词')
@section('section', '内容管理 / 单词管理')
@section('description', $word ? '单词 ID #'.$word->id.' · 编辑资料和详解' : '添加新单词与对应详解。')
@section('actions')<a class="btn secondary" href="{{ route('admin.words.index') }}">← 返回列表</a>@endsection
@section('content')
@php
    $editing = (bool) $word;
    $detail = $editing ? $word->detail : null;
    $detailType = old('detailType', $detail->type ?? 'richtext');
    $detailHtml = app(\App\Services\HtmlContentSanitizer::class)->clean((string) old('detailHtml', $detail->html ?? ''));
@endphp
<div class="split-panel"><div class="form-stack">
    <section class="card"><div class="card-header"><div><h2>单词资料</h2><p>基本释义、发音和资料标识</p></div></div><div class="card-body">
        <form method="post" action="{{ $editing ? route('admin.word.update', $word->id) : route('admin.word.create') }}" class="rich-form">@csrf @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <div class="form-field"><label for="headword">单词 *</label><input class="input" id="headword" name="headword" value="{{ old('headword', $word->headword ?? '') }}" required></div>
                <div class="form-field"><label for="ipa">音标</label><input class="input" id="ipa" name="ipa" value="{{ old('ipa', $word->ipa ?? '') }}"></div>
                <div class="form-field"><label for="partOfSpeech">词性</label><input class="input" id="partOfSpeech" name="partOfSpeech" value="{{ old('partOfSpeech', $word->part_of_speech ?? '') }}" placeholder="n. / v. / adj."></div>
                <div class="form-field"><label for="meaning">简明释义 *</label><input class="input" id="meaning" name="meaning" value="{{ old('meaning', $word->meaning ?? '') }}" required></div>
                <div class="form-field"><label for="frequencyLabel">频率标签</label><input class="input" id="frequencyLabel" name="frequencyLabel" value="{{ old('frequencyLabel', $word->frequency_label ?? '') }}"></div>
                <div class="form-field"><label for="audioAsset">发音资源路径</label><input class="input" id="audioAsset" name="audioAsset" value="{{ old('audioAsset', $word->audio_asset ?? '') }}"></div>
                <div class="form-field"><label for="slug">内容标识 slug</label><input class="input" id="slug" name="slug" value="{{ old('slug', $word->slug ?? '') }}"></div>
                <div class="form-field"><label for="detailType">详解形式</label><select class="select content-type" id="detailType" name="detailType"><option value="richtext" @selected($detailType === 'richtext')>富文本详解</option><option value="officialAccount" @selected($detailType === 'officialAccount')>公众号文章链接</option></select></div>
            </div>
            <div class="form-field type-rich" style="margin-top:22px"><label>单词详解正文</label><div class="toolbar"><button type="button" data-command="bold"><b>粗体</b></button><button type="button" data-command="italic"><i>斜体</i></button><button type="button" data-command="insertUnorderedList">列表</button><button type="button" data-command="formatBlock" data-value="h3">小标题</button><button type="button" data-command="removeFormat">清除格式</button></div><div class="rich-editor" contenteditable="true" data-placeholder="填写词义辨析、用法和例句…">{!! $detailHtml !!}</div><textarea hidden class="rich-value" name="detailHtml">{{ $detailHtml }}</textarea></div>
            <div class="form-grid type-official" style="margin-top:22px"><div class="form-field wide"><label>公众号文章链接</label><input class="input" type="url" name="detailUrl" value="{{ old('detailUrl', $detail->url ?? '') }}"></div><div class="form-field"><label>文章标题</label><input class="input" name="detailTitle" value="{{ old('detailTitle', $detail->title ?? '') }}"></div><div class="form-field"><label>摘要</label><input class="input" name="detailSummary" value="{{ old('detailSummary', $detail->summary ?? '') }}"></div></div>
            <div class="section-actions"><button class="btn" type="submit">保存单词与详解</button></div>
        </form>
    </div></section>
</div><aside class="form-stack">
    <section class="card"><div class="card-header"><h2>发布状态</h2></div><div class="card-body">@if($editing)<p><span class="status {{ $word->status }}">{{ $word->status === 'published' ? '已发布' : '草稿' }}</span></p><p class="helper">发布后，该单词可被精读文章和每日学习引用。</p>
        @if($word->status === 'published')<form method="post" action="{{ route('admin.word.unpublish', $word->id) }}">@csrf<button class="btn danger" type="submit">下架单词</button></form>@else<form method="post" action="{{ route('admin.word.publish', $word->id) }}">@csrf<button class="btn" type="submit">发布单词</button></form>@endif
        @else<p class="helper">保存后默认为草稿。</p>@endif</div></section>
    @if($editing)<section class="card"><div class="card-header"><h2>单词信息</h2></div><div class="card-body"><div class="helper">ID：#{{ $word->id }}</div><div class="helper" style="margin-top:8px">最后更新：{{ \Illuminate\Support\Carbon::parse($word->updated_at, 'UTC')->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s') }}</div></div></section>@endif
</aside></div>
@endsection
@push('scripts')<script src="{{ asset('admin-assets/editor.js') }}" defer></script>@endpush
