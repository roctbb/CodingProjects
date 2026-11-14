<x-gc-form-section title="Глава программы">
<x-gc-field name="name" label="Название главы"><input id="name" name="name" type="text" class="form-control" value="{{ old('name', $chapter?->name ?? '') }}" required maxlength="255"></x-gc-field>
<x-gc-field name="description" label="Описание главы"><textarea id="description" name="description" class="form-control" rows="4"  >{{ old('description', $chapter?->description ?? '') }}</textarea></x-gc-field>
</x-gc-form-section>