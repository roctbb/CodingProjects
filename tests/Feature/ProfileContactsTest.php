<?php

namespace Tests\Feature;

use App\Support\ContactLinks;
use App\User;
use Tests\TestCase;

class ProfileContactsTest extends TestCase
{
    private function contacts(string $role = 'student', bool $owner = false, bool $hiddenBirthday = false, array $overrides = [], string $partial = 'contacts'): string
    {
        $user = (new User())->forceFill(array_merge([
            'id' => 666, 'email' => 'person@example.test', 'telegram' => '@example_person', 'git' => 'https://github.com/example',
            'birthday' => '2012-06-12', 'birthday_hidden' => $hiddenBirthday, 'school' => 'Школа', 'grade_year' => 2030,
            'telegram_chat_id' => 'private-chat-id', 'telegram_link_token' => 'private-link-token',
        ], $overrides));
        $guest = (new User())->forceFill(['id' => $owner ? 666 : 1, 'role' => $role]);

        return view('profile.partials.'.$partial, ['user' => $user, 'guest' => $guest, 'isOwner' => $owner])->render();
    }

    public function test_contacts_are_open_with_actionable_public_links(): void
    {
        $html = $this->contacts();
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringContainsString('https://t.me/example_person', $html);
        $this->assertStringContainsString('https://github.com/example', $html);
        $this->assertStringNotContainsString('person@example.test', $html);
        $this->assertStringNotContainsString('private-chat-id', $html);
        $this->assertStringNotContainsString('private-link-token', $html);
    }

    public function test_email_keeps_existing_owner_teacher_admin_visibility(): void
    {
        foreach ([['student', true], ['teacher', false], ['admin', false]] as [$role, $owner]) {
            $this->assertStringContainsString('mailto:person@example.test', $this->contacts($role, $owner));
        }
        $this->assertStringNotContainsString('person@example.test', $this->contacts('student', false));
    }

    public function test_birthdays_move_to_about_and_hidden_dates_remain_hidden(): void
    {
        $this->assertStringNotContainsString('12.06.2012', $this->contacts());
        $this->assertStringContainsString('12.06.2012', $this->contacts(partial: 'about'));
        foreach (['student', 'teacher', 'admin'] as $role) {
            $this->assertStringNotContainsString('12.06.2012', $this->contacts($role, false, true, partial: 'about'));
        }
    }

    public function test_link_labels_preserve_identity_without_long_protocol_prefixes(): void
    {
        $this->assertSame('example', ContactLinks::label('https://github.com/example'));
        $this->assertSame('@example_person', ContactLinks::label('https://t.me/example_person', true));
        $this->assertSame('git.example.test/team/project', ContactLinks::label('https://git.example.test/team/project'));
        $this->assertSame('javascript:alert(1)', ContactLinks::label('javascript:alert(1)'));
        $this->assertStringContainsString('>example</a>', $this->contacts());
    }

    public function test_unsafe_schemes_and_unrecognized_handles_remain_plain_text(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', '//example.com', 'file:///etc/passwd'] as $value) {
            $this->assertNull(ContactLinks::web($value));
            $this->assertNull(ContactLinks::telegram($value));
        }
        $html = $this->contacts(overrides: ['telegram' => '<script>alert(1)</script>', 'git' => 'javascript:alert(1)']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('https://t.me/example_person', $this->contacts(overrides: ['telegram' => 't.me/example_person']));
    }
}
