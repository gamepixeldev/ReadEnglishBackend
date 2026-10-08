<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>内容管理 · 精读英语</title>
    <style>
        *{box-sizing:border-box}[hidden]{display:none!important}body{margin:0;background:#f4f3ee;color:#273b33;font:14px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}button,input,select,textarea{font:inherit}a{color:inherit;text-decoration:none}.topbar{height:68px;background:#fff;border-bottom:1px solid #e8e9e3;display:flex;align-items:center;justify-content:space-between;padding:0 max(24px,calc((100vw - 1440px)/2));position:sticky;top:0;z-index:4}.brand{display:flex;gap:12px;align-items:center;font-weight:750}.brand-mark{width:36px;height:36px;border-radius:11px;background:#e3f1e9;color:#358261;display:grid;place-items:center;font-size:19px}.brand small{display:block;color:#929a95;font-size:10px;letter-spacing:.14em;font-weight:650}.userbar{display:flex;align-items:center;gap:16px;color:#758079}.userbar a,.link-button{color:#378562}.userbar form{display:inline}.link-button{border:0;background:none;cursor:pointer;padding:0}.layout{max-width:1440px;margin:30px auto;padding:0 24px;display:grid;grid-template-columns:280px minmax(0,1fr);gap:24px}.sidebar,.panel{background:white;border:1px solid #e7e8e1;border-radius:18px}.sidebar{padding:18px;height:calc(100vh - 126px);position:sticky;top:92px;display:flex;flex-direction:column;min-height:400px}.side-tabs{display:flex;background:#f4f5f1;border-radius:11px;padding:4px;gap:4px}.side-tabs a{flex:1;text-align:center;padding:9px 4px;border-radius:8px;color:#78827b;font-weight:650}.side-tabs a.on{background:#fff;color:#30815d;box-shadow:0 1px 5px #203c2a12}.side-head{display:flex;justify-content:space-between;align-items:center;margin:20px 2px 10px}.side-head strong{font-size:13px}.new-link{font-size:12px;color:#378562}.entry-list{overflow:auto}.entry{display:block;padding:12px;border-radius:11px;margin:3px 0}.entry:hover,.entry.on{background:#eff7f1}.entry-title{display:block;font-weight:650;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.entry-meta{display:flex;gap:8px;color:#929b95;font-size:11px;margin-top:4px}.pill{padding:1px 7px;border-radius:20px;background:#f1f2ee}.pill.published{background:#e5f5eb;color:#33815b}.main{min-width:0}.page-title{margin:0 0 5px;font-size:24px}.page-sub{margin:0 0 20px;color:#8a948d}.notice{padding:11px 14px;border-radius:11px;margin-bottom:16px;background:#e9f6ed;color:#307650}.notice.error{background:#fff0ed;color:#a94e45}.stack{display:grid;gap:18px}.panel{padding:22px}.panel-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px}.panel h2{font-size:16px;margin:0}.panel-note{font-size:12px;color:#929a95;margin:4px 0 0}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{display:grid;gap:6px}.field.full{grid-column:1/-1}.field label{font-size:12px;font-weight:700;color:#53645b}.field input,.field select,.field textarea{width:100%;border:1px solid #dfe2dc;border-radius:10px;background:#fff;padding:10px 12px;outline:0;color:#263a32}.field input,.field select{height:42px}.field textarea{min-height:90px;resize:vertical}.field input:focus,.field select:focus,.field textarea:focus,.rich-editor:focus{border-color:#5caa82;box-shadow:0 0 0 3px #5caa821a}.paragraph{border:1px solid #e7e9e2;border-radius:13px;padding:14px;margin:12px 0}.paragraph-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;color:#5e7165;font-weight:700}.paragraph textarea{min-height:100px}.toolbar{display:flex;flex-wrap:wrap;gap:6px;padding:8px;background:#f5f6f2;border:1px solid #e0e3dc;border-bottom:0;border-radius:10px 10px 0 0}.toolbar button,.ghost{border:1px solid #dfe3dc;border-radius:8px;background:#fff;padding:6px 9px;color:#53645b;cursor:pointer;font-size:12px}.rich-editor{min-height:180px;padding:13px;border:1px solid #dfe3dc;border-radius:0 0 10px 10px;outline:0;overflow:auto}.rich-editor:empty:before{content:attr(data-placeholder);color:#a5ada7}.sub-form{border-top:1px solid #edf0e9;padding-top:18px;margin-top:18px}.toolbar-row{display:flex;justify-content:space-between;align-items:center;margin:12px 0}.actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-top:18px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:10px 15px;background:#398867;color:#fff;font-weight:700;cursor:pointer}.btn.secondary{background:#edf4ef;color:#397958}.btn.outline{background:white;color:#617068;border:1px solid #dfe4dc}.btn.danger{background:#fff0ed;color:#a14f47}.btn.small{padding:7px 10px;font-size:12px}.word-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:7px;max-height:250px;overflow:auto;padding:10px;border:1px solid #e6e8e1;border-radius:11px}.word-option{display:flex;gap:8px;align-items:center;padding:8px;border-radius:8px;background:#f8f9f6}.word-option small{color:#8d9790}.empty{padding:40px 18px;text-align:center;color:#8b958e}.empty b{display:block;color:#344b40;margin-bottom:6px}.helper{color:#929b95;font-size:12px}.error-list{color:#aa5149;margin:0;padding-left:18px}.password-form{display:flex;gap:8px;align-items:center}.password-form input{max-width:190px;height:34px;border:1px solid #dfe2dc;border-radius:8px;padding:5px 8px}.mobile-menu{display:none}
        @media(max-width:850px){.layout{grid-template-columns:1fr;margin:16px auto;padding:0 12px}.sidebar{height:auto;position:static;padding:12px}.entry-list{display:flex;gap:6px;max-height:150px}.entry{min-width:190px}.main{min-width:0}.topbar{padding:0 14px}.userbar{gap:8px}.user-email{display:none}.panel{padding:16px}.field-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.password-form input{max-width:130px}}
    </style>
</head>
<body>
<header class="topbar"><div class="brand"><span class="brand-mark">阅</span><span>精读英语<small>CONTENT STUDIO</small></span></div><div class="userbar"><span class="user-email">{{ auth()->user()->email }}</span><a href="{{ route('admin.password') }}">修改密码</a><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="link-button">退出</button></form></div></header>
<div class="layout">
    <aside class="sidebar">
        <nav class="side-tabs"><a class="{{ $tab === 'articles' ? 'on' : '' }}" href="{{ route('admin.content', ['tab'=>'articles']) }}">精读文章</a><a class="{{ $tab === 'words' ? 'on' : '' }}" href="{{ route('admin.content', ['tab'=>'words']) }}">单词</a></nav>
        <div class="side-head"><strong>{{ $tab === 'articles' ? '文章列表' : '单词列表' }}</strong><a class="new-link" href="{{ route('admin.content', ['tab'=>$tab, 'id'=>'new']) }}">＋ 新建</a></div>
        <div class="entry-list">
            @if($tab === 'articles')
                @forelse($articles as $item)<a class="entry {{ $article && $article->id === $item->id ? 'on' : '' }}" href="{{ route('admin.content', ['tab'=>'articles','id'=>$item->id]) }}"><span class="entry-title">{{ $item->translation ?: $item->title }}</span><span class="entry-meta"><span>#{{ $item->id }}</span><span class="pill {{ $item->status }}">{{ $item->status === 'published' ? '已发布' : '草稿' }}</span></span></a>@empty<div class="empty">还没有文章</div>@endforelse
            @else
                @forelse($words as $item)<a class="entry {{ $word && $word->id === $item->id ? 'on' : '' }}" href="{{ route('admin.content', ['tab'=>'words','id'=>$item->id]) }}"><span class="entry-title">{{ $item->headword }} <small>{{ $item->meaning }}</small></span><span class="entry-meta"><span>#{{ $item->id }}</span><span class="pill {{ $item->status }}">{{ $item->status === 'published' ? '已发布' : '草稿' }}</span></span></a>@empty<div class="empty">还没有单词</div>@endforelse
            @endif
        </div>
    </aside>
    <main class="main">
        <h1 class="page-title">{{ $tab === 'articles' ? '文章内容' : '单词内容' }}</h1><p class="page-sub">编辑内容并保存为草稿；检查后再发布到学习端。</p>
        @if(session('success'))<div class="notice">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice error"><ul class="error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @if($tab === 'articles')
            @php
                $editing = $article && request('id') !== 'new';
                $paragraphRows = old('paragraphs', $editing ? $article->body : [['sentences'=>[''], 'translation'=>'']]);
            @endphp
            @if(!$article && request('id') !== 'new')<section class="panel empty"><b>还没有精读文章</b>点击左侧“新建”开始添加。</section>@else
            <div class="stack">
                <section class="panel">
                    <div class="panel-head"><div><h2>文章信息与正文</h2><p class="panel-note">每段英文按句分行，中文填写整段翻译。</p></div>@if($editing)<span class="pill {{ $article->status }}">{{ $article->status === 'published' ? '已发布' : '草稿' }}</span>@endif</div>
                    <form method="post" action="{{ $editing ? route('admin.article.update',$article->id) : route('admin.article.create') }}">
                        @csrf @if($editing) @method('PUT') @endif
                        <div class="field-grid">
                            <div class="field"><label>英文标题 *</label><input name="title" value="{{ old('title',$editing ? $article->title : '') }}" required></div>
                            <div class="field"><label>中文标题</label><input name="translation" value="{{ old('translation',$editing ? $article->translation : '') }}"></div>
                            <div class="field"><label>难度</label><select name="level"><option value="">请选择</option>@foreach(['初级','高级'] as $level)<option value="{{ $level }}" @selected(old('level',$editing ? $article->level : '') === $level)>{{ $level }}</option>@endforeach</select></div>
                            <div class="field"><label>预计阅读分钟</label><input name="minutes" type="number" min="1" max="65535" value="{{ old('minutes',$editing ? $article->minutes : '') }}"></div>
                            <div class="field"><label>内容标识 slug（可选）</label><input name="slug" value="{{ old('slug',$editing ? $article->slug : '') }}" placeholder="例如 blue-umbrella"></div>
                            <div class="field"><label>封面资源路径（可选）</label><input name="coverAsset" value="{{ old('coverAsset',$editing ? $article->cover_asset : '') }}"></div>
                            <div class="field"><label>版权类型 *</label><select name="rightsType"><option value="">请选择</option>@foreach(['original'=>'原创','public_domain'=>'公版','licensed'=>'已授权'] as $value=>$label)<option value="{{ $value }}" @selected(old('rightsType',$editing ? $article->rights_type : '') === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="field"><label>版权来源说明 *</label><input name="rightsNote" value="{{ old('rightsNote',$editing ? $article->rights_note : '') }}" placeholder="作者、来源或授权证明位置"></div>
                        </div>
                        <div id="paragraphs">
                            @foreach($paragraphRows as $index=>$paragraph)
                                @php $sentencesText = is_array($paragraph['sentences'] ?? null) ? implode("\n",$paragraph['sentences']) : ($paragraph['sentences'] ?? ''); @endphp
                                <div class="paragraph"><div class="paragraph-head"><span>第 {{ $index+1 }} 段</span><button class="ghost remove-paragraph" type="button">移除段落</button></div><div class="field"><label>英文句子（一行一句）</label><textarea name="paragraphs[{{ $index }}][sentences]" required>{{ old("paragraphs.$index.sentences",$sentencesText) }}</textarea></div><div class="field" style="margin-top:10px"><label>本段中文翻译</label><textarea name="paragraphs[{{ $index }}][translation]" required>{{ old("paragraphs.$index.translation",$paragraph['translation'] ?? '') }}</textarea></div></div>
                            @endforeach
                        </div>
                        <button type="button" id="add-paragraph" class="btn outline small">＋ 添加段落</button>
                        <div class="actions"><button class="btn">保存文章草稿</button></div>
                    </form>
                </section>
                @if($editing)
                    <section class="panel"><div class="panel-head"><div><h2>文章语法解析</h2><p class="panel-note">文章解析独立于语法课程；可以用富文本或关联公众号文章。</p></div></div>
                        @php $analysisType = old('type',$article->analysis->type ?? 'richtext'); $analysisHtml = app(\App\Services\HtmlContentSanitizer::class)->clean((string) old('html',$article->analysis->html ?? '')); @endphp
                        <form method="post" action="{{ route('admin.article.analysis',$article->id) }}" class="rich-form">@csrf @method('PUT')
                            <div class="field"><label>内容形式</label><select name="type" class="content-type"><option value="richtext" @selected($analysisType==='richtext')>富文本解析</option><option value="officialAccount" @selected($analysisType==='officialAccount')>公众号文章</option></select></div>
                            <div class="field type-rich"><label>解析正文</label><div class="toolbar"><button type="button" data-command="bold"><b>粗体</b></button><button type="button" data-command="italic"><i>斜体</i></button><button type="button" data-command="insertUnorderedList">项目列表</button><button type="button" data-command="formatBlock" data-value="h3">小标题</button><button type="button" data-command="removeFormat">清除格式</button></div><div class="rich-editor" contenteditable="true" data-placeholder="在这里编写文章语法解析…">{!! $analysisHtml !!}</div><textarea hidden name="html" class="rich-value">{{ $analysisHtml }}</textarea></div>
                            <div class="field type-official"><label>公众号文章链接</label><input name="url" type="url" value="{{ old('url',$article->analysis->url ?? '') }}"></div>
                            <div class="field-grid type-official"><div class="field"><label>文章标题</label><input name="title" value="{{ old('title',$article->analysis->title ?? '') }}"></div><div class="field"><label>摘要</label><input name="summary" value="{{ old('summary',$article->analysis->summary ?? '') }}"></div></div>
                            <div class="actions"><button class="btn">保存解析</button><span class="helper">保存后会经过安全清理。</span></div>
                        </form>
                    </section>
                    <section class="panel"><div class="panel-head"><div><h2>重点词汇</h2><p class="panel-note">勾选词条，列表顺序就是文章内的展示顺序。</p></div><span class="helper">{{ count($article->wordIds) }} 个已选</span></div>
                        <form method="post" action="{{ route('admin.article.words',$article->id) }}">@csrf @method('PUT')<div class="word-picker">@foreach($words as $availableWord)<label class="word-option"><input type="checkbox" name="wordIds[]" value="{{ $availableWord->id }}" @checked(in_array($availableWord->id,$article->wordIds))><span>{{ $availableWord->headword }} <small>{{ $availableWord->meaning }}</small></span></label>@endforeach</div><div class="actions"><button class="btn secondary">保存重点词顺序</button><a class="helper" href="{{ route('admin.content',['tab'=>'words','id'=>'new']) }}">＋ 先添加新单词</a></div></form>
                    </section>
                    <section class="panel"><h2>发布状态</h2><p class="panel-note">发布后可在公开精读列表中看到；下架不会删除文章数据。</p><div class="actions">
                        @if($article->status === 'published')<form method="post" action="{{ route('admin.article.unpublish',$article->id) }}">@csrf<button class="btn danger">下架文章</button></form>@else<form method="post" action="{{ route('admin.article.publish',$article->id) }}">@csrf<button class="btn">发布文章</button></form>@endif
                    </div></section>
                @endif
            </div>
            @endif
        @else
            @php $editing = $word && request('id') !== 'new'; $detail = $editing ? $word->detail : null; $detailType = old('detailType',$detail->type ?? 'richtext'); $detailHtml = app(\App\Services\HtmlContentSanitizer::class)->clean((string) old('detailHtml',$detail->html ?? '')); @endphp
            @if(!$word && request('id') !== 'new')<section class="panel empty"><b>还没有单词</b>点击左侧“新建”开始添加。</section>@else
            <div class="stack">
                <section class="panel"><div class="panel-head"><div><h2>单词资料与详解</h2><p class="panel-note">单词基础信息和长篇详解分别保存。</p></div>@if($editing)<span class="pill {{ $word->status }}">{{ $word->status === 'published' ? '已发布' : '草稿' }}</span>@endif</div>
                    <form method="post" action="{{ $editing ? route('admin.word.update',$word->id) : route('admin.word.create') }}" class="rich-form">@csrf @if($editing) @method('PUT') @endif
                        <div class="field-grid">
                            <div class="field"><label>单词 *</label><input name="headword" value="{{ old('headword',$editing ? $word->headword : '') }}" required></div>
                            <div class="field"><label>音标</label><input name="ipa" value="{{ old('ipa',$editing ? $word->ipa : '') }}"></div>
                            <div class="field"><label>词性</label><input name="partOfSpeech" value="{{ old('partOfSpeech',$editing ? $word->part_of_speech : '') }}" placeholder="n. / v. / adj."></div>
                            <div class="field"><label>简明释义 *</label><input name="meaning" value="{{ old('meaning',$editing ? $word->meaning : '') }}" required></div>
                            <div class="field"><label>频率标签</label><input name="frequencyLabel" value="{{ old('frequencyLabel',$editing ? $word->frequency_label : '') }}"></div>
                            <div class="field"><label>发音资源路径</label><input name="audioAsset" value="{{ old('audioAsset',$editing ? $word->audio_asset : '') }}"></div>
                            <div class="field"><label>内容标识 slug（可选）</label><input name="slug" value="{{ old('slug',$editing ? $word->slug : '') }}"></div>
                            <div class="field"><label>详解形式</label><select name="detailType" class="content-type"><option value="richtext" @selected($detailType==='richtext')>富文本详解</option><option value="officialAccount" @selected($detailType==='officialAccount')>公众号文章</option></select></div>
                        </div>
                        <div class="field type-rich" style="margin-top:15px"><label>单词详解正文</label><div class="toolbar"><button type="button" data-command="bold"><b>粗体</b></button><button type="button" data-command="italic"><i>斜体</i></button><button type="button" data-command="insertUnorderedList">项目列表</button><button type="button" data-command="formatBlock" data-value="h3">小标题</button><button type="button" data-command="removeFormat">清除格式</button></div><div class="rich-editor" contenteditable="true" data-placeholder="添加词义辨析、构词、用法和例句…">{!! $detailHtml !!}</div><textarea hidden name="detailHtml" class="rich-value">{{ $detailHtml }}</textarea></div>
                        <div class="field type-official"><label>公众号文章链接</label><input name="detailUrl" type="url" value="{{ old('detailUrl',$detail->url ?? '') }}"></div>
                        <div class="field-grid type-official"><div class="field"><label>文章标题</label><input name="detailTitle" value="{{ old('detailTitle',$detail->title ?? '') }}"></div><div class="field"><label>摘要</label><input name="detailSummary" value="{{ old('detailSummary',$detail->summary ?? '') }}"></div></div>
                        <div class="actions"><button class="btn">保存单词和详解</button></div>
                    </form>
                </section>
                @if($editing)<section class="panel"><h2>发布状态</h2><p class="panel-note">发布后可以在精读重点词和每日单词内容中引用。</p><div class="actions">
                    @if($word->status==='published')<form method="post" action="{{ route('admin.word.unpublish',$word->id) }}">@csrf<button class="btn danger">下架单词</button></form>@else<form method="post" action="{{ route('admin.word.publish',$word->id) }}">@csrf<button class="btn">发布单词</button></form>@endif
                </div></section>@endif
            </div>
            @endif
        @endif
    </main>
</div>
<script>
document.querySelectorAll('.content-type').forEach(select=>{const form=select.closest('form');const sync=()=>{form.querySelectorAll('.type-rich').forEach(el=>el.hidden=select.value!=='richtext');form.querySelectorAll('.type-official').forEach(el=>el.hidden=select.value!=='officialAccount')};select.addEventListener('change',sync);sync()});
document.querySelectorAll('.rich-form').forEach(form=>form.addEventListener('submit',()=>form.querySelectorAll('.rich-editor').forEach((editor,index)=>{const field=form.querySelectorAll('.rich-value')[index];if(field)field.value=editor.innerHTML})));
document.querySelectorAll('.toolbar button').forEach(button=>button.addEventListener('click',()=>{const editor=button.closest('.field').querySelector('.rich-editor');editor.focus();document.execCommand(button.dataset.command,false,button.dataset.value||null)}));
const paragraphRoot=document.getElementById('paragraphs');
document.getElementById('add-paragraph')?.addEventListener('click',()=>{const index=paragraphRoot.querySelectorAll('.paragraph').length;const row=document.createElement('div');row.className='paragraph';row.innerHTML=`<div class="paragraph-head"><span>第 ${index+1} 段</span><button class="ghost remove-paragraph" type="button">移除段落</button></div><div class="field"><label>英文句子（一行一句）</label><textarea name="paragraphs[${index}][sentences]" required></textarea></div><div class="field" style="margin-top:10px"><label>本段中文翻译</label><textarea name="paragraphs[${index}][translation]" required></textarea></div>`;paragraphRoot.appendChild(row);row.querySelector('textarea').focus()});
paragraphRoot?.addEventListener('click',event=>{if(event.target.closest('.remove-paragraph')){const rows=paragraphRoot.querySelectorAll('.paragraph');if(rows.length>1)event.target.closest('.paragraph').remove()}});
document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const rows=paragraphRoot?.querySelectorAll('.paragraph');rows?.forEach((row,index)=>row.querySelectorAll('textarea').forEach((field,fieldIndex)=>field.name=`paragraphs[${index}][${fieldIndex===0?'sentences':'translation'}]`))}));
</script>
</body>
</html>
