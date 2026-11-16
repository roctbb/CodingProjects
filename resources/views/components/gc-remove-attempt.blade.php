@props(['course', 'task', 'attempt', 'student', 'taskName'])
<form method="post" action="{{ route('solutions.destroy', ['course'=>$course,'task'=>$task,'attempt'=>$attempt]) }}" class="m-0"
      data-confirm="Удалить решение {{ $student }} по задаче «{{ $taskName }}»? Эта сдача и её проверки будут удалены без возможности восстановления. Баллы и прогресс пересчитаются по оставшимся решениям. Операции с GC сохранятся.">
    @csrf @method('DELETE')
    <input type="hidden" name="confirm" value="1"><input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
    <button type="submit" class="btn btn-outline-secondary btn-sm gc-icon-button" title="Удалить решение" aria-label="Удалить решение {{ $student }} по задаче {{ $taskName }}"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
</form>
