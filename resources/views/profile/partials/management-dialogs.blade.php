    @if($guest->role == 'admin')
    {{-- Add course modal --}}
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content overflow-hidden">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-graduation-cap"></i></span>
                        <h5 class="modal-title">Добавление курса</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form action="{{ url('/insider/profile/'.$user->id.'/course') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="completed-course-name" class="form-label">Название</label>
                            <input type="text" name="name" class="form-control" id="completed-course-name" maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="completed-course-mark" class="form-label">Оценка за курс</label>
                            <input type="text" name="mark" maxlength="255" class="form-control" id="completed-course-mark">
                            <div class="form-text">Завершение курса не добавляет очки опыта.</div>
                        </div>
                    </div>
                    <div class="modal-footer gc-form-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @endif

    @if ($canManageMoney)
        {{-- GC operation modal --}}
        <div class="modal fade" id="addMoney" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content overflow-hidden">
                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-coins"></i></span>
                            <div>
                                <h5 class="modal-title">Операция с GC</h5>
                                <div class="text-muted small">Плюс начисляет, минус списывает монеты.</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <form action="{{ url('/insider/profile/'.$user->id.'/money') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="money-description" class="form-label">Причина</label>
                                <input type="text" name="description" class="form-control" id="money-description" maxlength="255" placeholder="Например: возврат, награда, списание за заказ">
                            </div>
                            <div>
                                <label for="money-amount" class="form-label">Изменение баланса</label>
                                <input type="number" name="amount" min="-10000" max="10000" step="1" class="form-control" id="money-amount" placeholder="Например: 15 или -220">
                                <div class="form-text">Для списания укажите отрицательное число, например −220.</div>
                            </div>
                        </div>
                        <div class="modal-footer gc-form-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-coin">Сохранить операцию</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
