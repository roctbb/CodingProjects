<x-gc-form-section title="Материал ступени" description="Содержание ступени общее для курсов этой программы.">
<x-gc-field name="name" label="Название ступени"><input id="name" name="name" type="text" class="form-control" value="{{ old('name', $step?->name ?? '') }}" required maxlength="255"></x-gc-field>
<x-gc-toggle name="notebook" label="Формат Jupyter Notebook" value="yes" :checked="$step?->is_notebook ?? false" data-notebook-toggle hint="В этом режиме материал содержит JSON файла .ipynb." />
<x-gc-markdown-field name="theory" label="Материал" :value="$step?->theory ?? ''" :notebook="true" />
</x-gc-form-section>
<x-gc-form-section title="Для преподавателя" description="Эти заметки не видны ученикам.">
<x-gc-markdown-field name="notes" label="Заметки преподавателя" :value="$step?->notes ?? ''" :compact="true" />
</x-gc-form-section>
<x-gc-form-section title="Видео">
<x-gc-field name="video_url" label="Ссылка на видео" hint="Ссылка на видеоматериал для этой ступени."><input id="video_url" name="video_url" type="text" class="form-control" value="{{ old('video_url', $step?->video_url ?? '') }}"  maxlength="512" inputmode="url"></x-gc-field>
</x-gc-form-section>