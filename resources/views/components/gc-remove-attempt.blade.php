@props(['course', 'task', 'attempt', 'student', 'taskName'])
<form method="post" action="{{ route('solutions.destroy', ['course'=>$course,'task'=>$task,'attempt'=>$attempt]) }}" class="m-0"
      data-confirm="Удалить решение {{ $student }} по задаче «{{ $taskName }}»? Эта сдача, её проверки и связанные достижения будут удалены без возможности восстановления. Начисленные за неё GC будут списаны с баланса. Баллы и прогресс пересчитаются по оставшимся решениям.">
    @csrf @method('DELETE')
    <input type="hidden" name="confirm" value="1"><input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
    <button type="submit" class="btn btn-outline-secondary btn-sm gc-icon-button" title="Удалить решение" aria-label="Удалить решение {{ $student }} по задаче {{ $taskName }}"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
</form>
