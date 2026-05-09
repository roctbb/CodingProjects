
    @if (\Request::is('insider/*'))
        <div class="modal fade step-task-modal" id="exampleModal" tabindex="-1" role="dialog"
             aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 rounded-3 shadow-sm overflow-hidden">
                    <div class="modal-header border-bottom p-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="gc-icon-tile flex-shrink-0"><i class="icon ion-android-add-circle"></i></span>
                            <h5 class="modal-title" id="exampleModalLabel">Добавление задачи</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <form class="step-task-modal__form" action="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/task')}}" method="POST">
                        <div class="modal-body p-3 p-md-4">
                            {{ csrf_field() }}
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label for="task-name" class="form-label">Название</label>
                                    <input type="text" name="name" class="form-control rounded-3" id="task-name"/>
                                    @error('name')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-sm-6 col-md-2">
                                    <label for="task-max-mark" class="form-label">XP</label>
                                    <input type="number" min="0" name="max_mark" class="form-control rounded-3" id="task-max-mark"/>
                                    @error('max_mark')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-sm-6 col-md-2">
                                    <label for="task-price" class="form-label">Премия</label>
                                    <input type="number" min="0" name="price" class="form-control rounded-3" id="task-price"/>
                                    @error('price')
                                        <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="task-text" class="form-label">Текст вопроса</label>
                                <textarea id="task-text" class="form-control rounded-3" data-markdown-editor name="text">{{old('text')}}</textarea>
                                @error('text')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="task-solution" class="form-label">Решение</label>
                                <textarea id="task-solution" class="form-control rounded-3" data-markdown-editor name="solution">{{ old('solution') }}</textarea>
                                @error('solution')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="gc-switch-card d-grid gap-2 mb-3">
                                <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                                    <input type="checkbox" class="form-check-input ms-0 mt-0" id="task-is-star" name="is_star" value="on"/>
                                    <label for="task-is-star" class="form-check-label">Дополнительное</label>
                                </div>
                                <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                                    <input type="checkbox" class="form-check-input ms-0 mt-0" id="task-is-hidden" name="is_hidden" value="on"/>
                                    <label for="task-is-hidden" class="form-check-label">Скрытая задача</label>
                                </div>
                                <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                                    <input type="checkbox" class="form-check-input ms-0 mt-0" id="task-is-code" name="is_code" value="on"/>
                                    <label for="task-is-code" class="form-check-label">Автопроверка</label>
                                </div>
                                <div class="form-check form-switch mb-0 ps-0 d-flex align-items-center gap-2">
                                    <input type="checkbox" class="form-check-input ms-0 mt-0" id="task-xp-booster-enabled" name="xp_booster_enabled" value="on"/>
                                    <label for="task-xp-booster-enabled" class="form-check-label">Разрешить бустер +5 XP</label>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label for="task-answer" class="form-label">Ответ</label>
                                <input type="text" name="answer" class="form-control rounded-3" id="task-answer"/>
                                @error('answer')
                                    <span class="text-danger small d-block mt-1"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer gc-form-footer step-modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-success rounded-3">Создать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
