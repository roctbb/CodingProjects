
    @if (\Request::is('insider/*'))
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Добавление задачи</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть">
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/task')}}"
                              method="POST"
	                            class="vstack gap-3">
                            {{ csrf_field() }}
	                            <div class="mb-3{{ $errors->has('name') ? ' is-invalid' : '' }}">
	                                <label for="name" class="form-label">Название</label>
	                                    <input type="text" name="name" class="form-control" id="name"/>
	                                    @if ($errors->has('name'))
	                                        <div class="invalid-feedback d-block">
	                                        <strong>{{ $errors->first('name') }}</strong>
	                                    </div>
	                                    @endif
	                            </div>
	                            <div class="mb-3">
                                <label for="consequences" class="form-label cp-label-spaced">Подтверждаемые результаты из
                                    <sup>
                                        <small>Core</small>
                                    </sup>:</label><br>
                                <select class="form-select" id="consequences" name="consequences[]" multiple>
                                    @foreach (\App\CoreNode::where('is_root', false)->where('version', 1)->get() as $node)
                                        <option data-tokens="{{ $node->id }}" value="{{ $node->id }}"
                                                data-subtext="{{$node->getParentLine()}}">{{$node->title}}</option>
                                    @endforeach
                                </select>
                            </div>
	                            <div class="mb-3{{ $errors->has('max_mark') ? ' is-invalid' : '' }}">
                                <label for="max_mark" class="form-label">Очков опыта</label>
                                    <input type="text" name="max_mark" class="form-control" id="max_mark"/>
                                    @if ($errors->has('max_mark'))
                                        <div class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('max_mark') }}</strong>
                                    </div>
                                    @endif
	                            </div>
	                            <div class="mb-3{{ $errors->has('text') ? ' is-invalid' : '' }}">
                                <label for="text" class="form-label">Текст вопроса</label>
                                                <textarea id="text" class="form-control"
                                                          name="text">{{old('text')}}</textarea>

                                    @if ($errors->has('text'))
                                        <div class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </div>
                                    @endif
	                            </div>

	                            <div class="mb-3">
                                <label for="solution" class="form-label">Решение</label>
                                <textarea id="solution" class="form-control"
                                          name="solution">@if (old('solution')!=""){{old('solution')}}@endif</textarea>
                                @if ($errors->has('solution'))
                                    <div class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('solution') }}</strong>
                                    </div>
                                @endif
                            </div>

                            <hr>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_star" name="is_star" value="on"/>
                                <label class="form-check-label" for="is_star">Дополнительное</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_hidden" name="is_hidden" value="on"/>
                                <label class="form-check-label" for="is_hidden">Скрытая задача</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_code" name="is_code" value="on"/>
                                <label class="form-check-label" for="is_code">Автопроверка</label>
                            </div>
                            <div class="mb-3{{ $errors->has('answer') ? ' is-invalid' : '' }}">
                                <label for="answer" class="form-label">Ответ</label>
                                    <input type="text" name="answer" class="form-control" id="answer"/>
                                    @if ($errors->has('answer'))
                                        <div class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('answer') }}</strong>
                                    </div>
                                    @endif
                            </div>
                            <div class="mb-3{{ $errors->has('price') ? ' is-invalid' : '' }}">
                                <label for="price" class="form-label">Премия</label>
                                    <input type="text" name="price" class="form-control" id="price"/>
                                    @if ($errors->has('price'))
                                        <div class="invalid-feedback d-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </div>
                                    @endif
                            </div>
                            
                            <div class="pt-2">
                                <button type="submit" class="btn btn-primary">Создать</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
