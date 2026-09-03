@props(['name' => 'content', 'id' => 'content', 'value' => '', 'required' => false, 'label' => 'Article content'])
@php $safeValue = app(\App\Services\RichTextSanitizer::class)->sanitize((string) $value); @endphp
<div class="portal-field rich-editor" data-rich-editor>
    <label id="{{ $id }}-label" for="{{ $id }}-visual">{{ $label }}</label>
    <div class="rich-editor-toolbar" role="toolbar" aria-label="Text formatting">
        <select class="portal-select" data-editor-block aria-label="Text style"><option value="p">Paragraph</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="pre">Code block</option></select>
        <button type="button" data-editor-command="bold" aria-label="Bold"><strong>B</strong></button><button type="button" data-editor-command="italic" aria-label="Italic"><em>I</em></button>
        <button type="button" data-editor-command="insertUnorderedList" aria-label="Bulleted list">• List</button><button type="button" data-editor-command="insertOrderedList" aria-label="Numbered list">1. List</button>
        <button type="button" data-editor-block-command="blockquote">Quote</button><button type="button" data-editor-link>Link</button><button type="button" data-editor-table>Table</button><button type="button" data-editor-image>Image</button><button type="button" data-editor-video>Video</button><button type="button" data-editor-command="removeFormat">Clear</button>
    </div>
    <div class="rich-editor-canvas" id="{{ $id }}-visual" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="{{ $id }}-label">{!! $safeValue !!}</div>
    <textarea name="{{ $name }}" id="{{ $id }}" hidden @if($required) required @endif>{{ $value }}</textarea>
    <p class="portal-help">Use headings, emphasis, lists, tables, quotations, code, links, captioned images, or approved YouTube/Vimeo embeds.</p>
    <x-portal.field-error :name="$name" />
</div>
