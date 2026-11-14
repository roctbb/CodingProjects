<section id="profile-about" class="{{ ($embedded ?? false) ? 'gc-profile-hero__section gc-profile-hero__about' : 'gc-card p-3' }}">
    <h2 class="gc-section-title">О себе</h2>
    @if($embedded ?? false)
        @php
            $biography = collect([$user->interests, $user->hobbies])->filter(fn ($value) => filled($value))->implode(' · ');
        @endphp
        @if(filled($biography))
            @if(mb_strlen($biography) > 180)
                <details class="gc-expandable-text gc-profile-bio">
                    <summary><span class="gc-expandable-text__preview">{{ \Illuminate\Support\Str::limit($biography, 180) }}</span><span class="gc-expandable-text__toggle"><span class="gc-expandable-text__more">Подробнее</span><span class="gc-expandable-text__less">Свернуть</span></span></summary>
                    <dl class="gc-facts mt-3 mb-0">
                        @if(filled($user->interests))<div><dt>Технические интересы</dt><dd class="gc-preserve-lines">{{ $user->interests }}</dd></div>@endif
                        @if(filled($user->hobbies))<div><dt>Увлечения</dt><dd class="gc-preserve-lines">{{ $user->hobbies }}</dd></div>@endif
                    </dl>
                </details>
            @else
                <dl class="gc-facts gc-profile-bio mb-0">
                    @if(filled($user->interests))<div><dt>Технические интересы</dt><dd class="gc-preserve-lines">{{ $user->interests }}</dd></div>@endif
                    @if(filled($user->hobbies))<div><dt>Увлечения</dt><dd class="gc-preserve-lines">{{ $user->hobbies }}</dd></div>@endif
                </dl>
            @endif
        @endif
        <div class="gc-profile-facts gc-help-text">
            @if(filled($user->school) || $user->grade_year !== null)<span>{{ $user->school }}@if(filled($user->school) && $user->grade_year !== null) · @endif @if($user->grade_year !== null){{ $user->gradeLabel() }}@endif</span>@endif
            @if($user->hasVisibleBirthday())<span>День рождения: {{ $user->birthday->format('d.m.Y') }}</span>@endif
        </div>
    @else
    <dl class="gc-facts gc-auto-columns gc-auto-columns--paired mb-0">
        @if(filled($user->school) || $user->grade_year !== null)
            <div>
                <dt>Место учёбы</dt>
                <dd>{{ $user->school }}@if(filled($user->school) && $user->grade_year !== null) · @endif
                    @if($user->grade_year !== null){{ $user->gradeLabel() }}@endif
                </dd>
            </div>
        @endif
        @if($user->hasVisibleBirthday())<div><dt>Дата рождения</dt><dd>{{ $user->birthday->format('d.m.Y') }}</dd></div>@endif
        @if(filled($user->interests))<div class="gc-facts__wide"><dt>Технические интересы</dt><dd>@if(mb_strlen($user->interests) > 220)<details class="gc-expandable-text"><summary><span class="gc-expandable-text__preview">{{ \Illuminate\Support\Str::limit($user->interests, 180) }}</span><span class="gc-expandable-text__toggle"><span class="gc-expandable-text__more">Полностью</span><span class="gc-expandable-text__less">Свернуть</span></span></summary><div class="gc-preserve-lines">{{ $user->interests }}</div></details>@else{{ $user->interests }}@endif</dd></div>@endif
        @if(filled($user->hobbies))<div class="gc-facts__wide"><dt>Увлечения</dt><dd>@if(mb_strlen($user->hobbies) > 220)<details class="gc-expandable-text"><summary><span class="gc-expandable-text__preview">{{ \Illuminate\Support\Str::limit($user->hobbies, 180) }}</span><span class="gc-expandable-text__toggle"><span class="gc-expandable-text__more">Полностью</span><span class="gc-expandable-text__less">Свернуть</span></span></summary><div class="gc-preserve-lines">{{ $user->hobbies }}</div></details>@else{{ $user->hobbies }}@endif</dd></div>@endif
    </dl>
    @endif
</section>
