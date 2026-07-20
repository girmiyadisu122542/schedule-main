<?php

namespace App\Http\Controllers\User\Action;

use App\Constants\Otp\OtpMethod;
use App\Http\Requests\User\CreateUserRequest;
use App\Models\User;
use App\Models\User\UserDetail;
use App\Services\User\SendCredentialsService;
use Constants\AppConstant;
use Exception;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Translation\Message;

trait CreateUserAction {

    /**
     * Create a new user or update an existing user
     *
     * @param \App\Http\Requests\User\CreateUserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createNewUser(CreateUserRequest $request): JsonResponse {
        $id = $request->input('id');
        $validated = $request->validated();
        $language = getCurrentLanguage($request);
        $photo = $request->file('photo');
        $photoPath = $photo && $photo->isValid() ? $photo->store(USER_PHOTO_LOCATION, DEFAULT_STORAGE) : null;

        $fullName = $validated['first_name'];
        $fullName .= ' ' . $validated['middle_name'];
        $fullName .= ' ' . $validated['last_name'];
        $fullName = trim($fullName);

        $userDetail = null;
        $userDetailInsertFields = [];
        $nationalId = $validated['national_id'] ?? null;

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            if ($nationalId) {
                /** ToDo: query needs to be updated */
                $userDetail = UserDetail::query()
                    ->where('national_id', $nationalId)
                    ->first();
            }

            if (!$userDetail) {
                $userDetailInsertFields = [
                    'first_name' => [$language => $validated['first_name']],
                    'middle_name' => [$language => $validated['middle_name']],
                    'last_name' => [$language => $validated['last_name']],
                    'bio' => isset($validated['bio']) ? [$language => $validated['bio']] : null,
                    'photo' => $photoPath,
                    'national_id' => $nationalId,
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'gender' => $validated['gender'],
                    'birth_date' => $validated['birth_date'] ?? now(),
                ];
            }

            $userInsertFields = [
                'photo' => $photoPath,
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'state' => USER_STATE_INACTIVE,
                'full_name' => [$language => $fullName],
                'user_id' => Auth::id(),
            ];

            if ($id) {
                $user = User::findOrFail($id);
                $userDetail = UserDetail::find($user->user_detail_id);
                if (!$userDetail) {
                    return Response::_404(Message::get('user_detail_not_found'));
                }

                $userDetailUpdateFields = [
                    'first_name' => [$language => $validated['first_name']],
                    'middle_name' => [$language => $validated['middle_name']],
                    'last_name' => [$language => $validated['last_name']],
                    'bio' => isset($validated['bio']) ? [$language => $validated['bio']] : null,
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'gender' => $validated['gender'],
                ];

                if ($nationalId) {
                    $userDetailUpdateFields['national_id'] = $nationalId;
                }

                $userInsertFields['state'] = $user->state;

                if ($photoPath) {
                    $userDetailUpdateFields['photo'] = $photoPath;
                }

                if (isset($validated['birth_date'])) {
                    $userDetailUpdateFields['birth_date'] = $validated['birth_date'];
                }

                $userInsertFields['user_detail_id'] = $userDetail->id;
                $userDetail->update($userDetailUpdateFields);
                $user->update($userInsertFields);
            } else {
                if (!$userDetail) {
                    $userDetail = UserDetail::create($userDetailInsertFields);
                }

                $userInsertFields['user_detail_id'] = $userDetail ? $userDetail->id : null;

                $password = Str::password(PASSWORD_LENGTH);
                $user = User::create($userInsertFields);
                $user->password = Hash::make($password);
            }

            $user->save();

            if (!$id) {
                $credentials = [
                    'name' => $fullName,
                    'email' => $user->email,
                    'password' => $password,
                    'phone' => $user->phone ?? null,
                ];

               // $sendCredentialsService = app(SendCredentialsService::class);
                //$sendCredentialsService->send($credentials, $language, OtpMethod::EMAIL);
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            if (isset($photoPath) && $photoPath) {
                Storage::disk(DEFAULT_STORAGE)->delete($photoPath);
            }

            return Response::_422([
                'msg' => Message::get('unable_to_create_user') . ': ' . $exception->getMessage(),
                'validated' => $validated,
            ]);
        }

        $bindings = ['name' => $fullName];
        $message = $id
            ? 'user_successfully_updated'
            : 'user_successfully_created';

        return Response::_201([
            'data' => $user,
            'message' => Message::get($message, $bindings),
        ]);
    }
}
