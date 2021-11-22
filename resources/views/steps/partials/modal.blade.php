
    @if (\Request::is('insider/*'))
        <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog"
             aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Добавление задачи</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/task')}}"
                              method="POST"
                              class="form-horizontal">
                            {{ csrf_field() }}
                            <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                                <label for="name" class="col-md-4">Название</label>

                                <div class="col-md-12">
                                    <input type="text" name="name" class="form-control" id="name"/>
                                    @if ($errors->has('name'))
                                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="consequences" style="padding-bottom: 10px;">Подтверждаемые результаты из
                                    <sup>
                                        <small>Core</small>
                                    </sup>:</label><br>
                                <select class="selectpicker  form-control" data-live-search="true" id="consequences"
                                        name="consequences[]" multiple data-width="auto">
                                    @foreach (\App\CoreNode::where('is_root', false)->where('version', 1)->get() as $node)
                                        <option data-tokens="{{ $node->id }}" value="{{ $node->id }}"
                                                data-subtext="{{$node->getParentLine()}}">{{$node->title}}</option>
                                    @endforeach
                                </select>

                                <script>
                                    $('.selectpicker').selectpicker();
                                </script>
                            </div>
                            <div class="form-group{{ $errors->has('max_mark') ? ' has-error' : '' }}">
                                <label for="max_mark" class="col-md-4">Очков опыта</label>

                                <div class="col-md-12">
                                    <input type="text" name="max_mark" class="form-control" id="max_mark"/>
                                    @if ($errors->has('max_mark'))
                                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('max_mark') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group{{ $errors->has('text') ? ' has-error' : '' }}">
                                <label for="text" class="col-md-4">Текст вопроса</label>

                                <div class="col-md-12">
                                                <textarea id="text" class="form-control"
                                                          name="text">{{old('text')}}</textarea>

                                    @if ($errors->has('text'))
                                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('text') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="solution">Решение</label>
                                <textarea id="solution" class="form-control"
                                          name="solution">@if (old('solution')!=""){{old('solution')}}@endif</textarea>
                                @if ($errors->has('solution'))
                                    <span class="help-block error-block">
                                        <strong>{{ $errors->first('solution') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <hr>
                            <div class="form-group">
                                <label for="is_star">Дополнительное</label>
                                <input type="checkbox" id="is_star" name="is_star" value="on"/>
                            </div>

                            <div class="form-group">
                                <label for="is_code">Автопроверка на Python</label>
                                <input type="checkbox" id="is_code" name="is_code" value="on"/>
                            </div>
                            <div class="form-group{{ $errors->has('answer') ? ' has-error' : '' }}">
                                <label for="answer" class="col-md-4">Ответ</label>

                                <div class="col-md-12">
                                    <input type="text" name="answer" class="form-control" id="answer"/>
                                    @if ($errors->has('answer'))
                                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('answer') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group{{ $errors->has('price') ? ' has-error' : '' }}">
                                <label for="price" class="col-md-4">Премия</label>

                                <div class="col-md-12">
                                    <input type="text" name="price" class="form-control" id="price"/>
                                    @if ($errors->has('price'))
                                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('price') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-success">Создать</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif