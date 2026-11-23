<li><h6 class="dropdown-header">Ресурсы</h6></li>
@if(Auth::check() && Auth::user()->role === 'admin')
<li><a class="dropdown-item" href="{{ url('/insider/material-review') }}"><i class="fas fa-book-open me-2"></i>Ревью программ</a></li>
@endif
<li><a class="dropdown-item" target="_blank" rel="noopener" href="https://storage.geekclass.ru"><i class="fas fa-cloud me-2"></i>Storage</a></li>
<li><a class="dropdown-item" target="_blank" rel="noopener" href="https://exam.geekclass.ru"><i class="fas fa-file-alt me-2"></i>Exam</a></li>
<li><a class="dropdown-item" target="_blank" rel="noopener" href="https://arena.geekclass.ru"><i class="fas fa-trophy me-2"></i>Arena</a></li>
<li><a class="dropdown-item" target="_blank" rel="noopener" href="https://battle.geekclass.ru"><i class="fas fa-code me-2"></i>CodeBattle</a></li>
<li><hr class="dropdown-divider"></li>
<li><button class="dropdown-item" id="gcThemeToggle" type="button"><i class="fas fa-moon me-2"></i>Тема</button></li>
<li><hr class="dropdown-divider"></li>
