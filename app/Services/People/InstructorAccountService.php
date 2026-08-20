<?php

namespace App\Services\People;

use App\Constants\Otp\OtpMethod;
use App\Models\People\Instructor;
use App\Models\Role\Role;
use App\Models\Role\UserRoleBinding;
use App\Models\User;
use App\Models\User\UserDetail;
use App\Services\User\SendCredentialsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Gives an instructor their portal account.
 *
 * An instructor and their login used to be two separate acts: someone entered
 * the person into the registry, someone else created a user, and a third step
 * married the two through `instructors.user_id`. That link is not cosmetic —
 * it is one of the three ways a user reaches a department
 * ({@see \App\Services\User\DepartmentScopeService::resolve()}), so an
 * instructor whose account was never attached could log in and be refused on
 * every offering, schedule and report screen for reasons nobody could see.
 *
 * So the account is now created WITH the instructor, from the same details, by
 * both the form and the importer.
 *
 * Everything here is deliberately non-destructive: it never overwrites an
 * existing link, never rewrites a password, and adopts a user who already owns
 * the email rather than failing on the unique index. That makes it safe to call
 * on every save, which is what lets an instructor created before this existed
 * pick up an account the moment an email is added to them.
 */
class InstructorAccountService {

    /**
     * Ensure this instructor has a portal account, creating one if needed.
     *
     * Callers run this INSIDE their own transaction: the account and the
     * instructor row are one fact, and a half-written pair is worse than
     * neither.
     *
     * @param \App\Models\People\Instructor $instructor
     * @param string|null $language recipient language for the credentials mail
     *
     * @return \App\Models\User|null the account, or null when none was needed
     */
    public function provision(Instructor $instructor, ?string $language = null): ?User {
        if ($instructor->user_id) {
            return null;
        }

        $email = trim((string) $instructor->email);
        if ($email === '') {
            return null;
        }

        // Adopt rather than duplicate. `users.email` is unique and the person
        // may already have been given a login by hand; a second row would fail
        // the index and lose the instructor with it.
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            // ...but only if that account is still free. `instructors.user_id`
            // is unique, so an account already belonging to somebody else
            // cannot be adopted — two instructors sharing one login is not a
            // thing. Skipping leaves this instructor accountless rather than
            // taking the whole save down; InstructorRequest refuses the
            // duplicate email up front, so reaching here means an importer or
            // a legacy row, and losing the row would be the worse outcome.
            $taken = Instructor::query()
                ->where('user_id', $existing->id)
                ->whereKeyNot($instructor->getKey())
                ->exists();

            if ($taken) {
                return null;
            }

            $instructor->user_id = $existing->id;
            $instructor->save();

            return $existing;
        }

        $fullName = $this->plainName($instructor, $language);
        [$first, $middle, $last] = $this->splitName($fullName);
        $languageKey = $language ?: ENGLISH_LANG_KEY;
        $phone = trim((string) $instructor->phone);

        $detail = UserDetail::create([
            'first_name' => [$languageKey => $first],
            'middle_name' => [$languageKey => $middle],
            'last_name' => [$languageKey => $last],
            // Not asked for on the instructor form and NOT NULL on the table.
            // Placeholders the person corrects on their own profile; inventing
            // a birth date is less wrong than blocking the account over it.
            'birth_date' => now(),
            'gender' => MALE,
            'phone' => $phone,
            'email' => $email,
        ]);

        $password = Str::password(PASSWORD_LENGTH);

        $user = User::create([
            'full_name' => [$languageKey => $fullName],
            'phone' => $phone,
            'email' => $email,
            // Same starting state as a hand-created account.
            'state' => USER_STATE_INACTIVE,
            'user_detail_id' => $detail->id,
            // The CREATOR of the row, not the person — matching users.user_id
            // everywhere else. Null on an import run with no authenticated user.
            'user_id' => Auth::id(),
        ]);

        $user->password = Hash::make($password);
        $user->save();

        $this->assignDefaultRole($user);

        $instructor->user_id = $user->id;
        $instructor->save();

        // Returns false rather than throwing when the mail cannot be sent, so a
        // mail server problem never rolls back the account it belongs to. The
        // user page can resend.
        app(SendCredentialsService::class)->send(
            [
                'name' => $fullName,
                'email' => $email,
                'password' => $password,
                'phone' => $phone ?: null,
            ],
            OtpMethod::EMAIL,
            $language,
        );

        return $user;
    }

    /**
     * Bind the teaching role, so the account is usable the moment it exists.
     *
     * A missing role is not fatal: the account is still worth having, and the
     * role can be granted on the user page. Silently skipping beats refusing to
     * create the instructor because a seeder has not run.
     *
     * @param \App\Models\User $user
     * @return void
     */
    private function assignDefaultRole(User $user): void {
        $role = Role::query()
            ->where('name->' . ENGLISH_LANG_KEY, DEFAULT_INSTRUCTOR_ROLE_NAME)
            ->first();

        if (!$role) {
            return;
        }

        UserRoleBinding::updateOrCreate(
            ['user_id' => $user->id, 'role_id' => $role->id],
            ['assigned_by' => Auth::id() ?? $user->id, 'starts_at' => now()],
        );
    }

    /**
     * The instructor's name as plain text.
     *
     * `full_name` is a language map; the account is created in the language the
     * request was made in, falling back to whatever the map does hold.
     *
     * @param \App\Models\People\Instructor $instructor
     * @param string|null $language
     *
     * @return string
     */
    private function plainName(Instructor $instructor, ?string $language): string {
        $name = $instructor->full_name;

        if (is_array($name)) {
            $name = $name[$language ?? ''] ?? $name[ENGLISH_LANG_KEY] ?? reset($name);
        }

        return trim((string) $name) ?: (string) $instructor->employee_no;
    }

    /**
     * Split one written name into the three columns `user_details` insists on.
     *
     * Ethiopian names are given-name + father + grandfather and are written as
     * one string everywhere in this system, which is why the instructor form
     * asks for one field. The split is positional: anything past the third word
     * stays with the last name rather than being dropped.
     *
     * @param string $fullName
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitName(string $fullName): array {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return match (count($parts)) {
            0 => ['-', '-', '-'],
            1 => [$parts[0], '-', '-'],
            2 => [$parts[0], $parts[1], '-'],
            default => [$parts[0], $parts[1], implode(' ', array_slice($parts, 2))],
        };
    }
}
