<?php

namespace App\Http\Controllers\User\Action;

use App\Http\Requests\User\UpdateProfileRequest;
use Constants\AppConstant;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Translation\Message;

trait UserProfileAction {

    /**
     * Update the authenticated user's profile (bio, photo, cover_photo).
     *
     * @param \App\Http\Requests\User\UpdateProfileRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse {
        $user = $request->user();
        $language = getCurrentLanguage($request);

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            if ($request->hasFile('photo')) {
                $user->photo = $request
                    ->file('photo')
                    ->store(USER_PHOTO_LOCATION, DEFAULT_STORAGE);
            }

            if ($request->hasFile('cover_photo')) {
                $user->cover_photo = $request
                    ->file('cover_photo')
                    ->store(USER_PHOTO_LOCATION, DEFAULT_STORAGE);
            }

            if ($request->filled('bio')) {
                $userDetail = $user->userDetail;

                if ($userDetail) {
                    $bio = $userDetail->bio ?? [];
                    $bio[$language] = $request->bio;
                    $userDetail->bio = $bio;
                    $userDetail->save();
                }
            }

            $user->save();

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (\Throwable $e) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            return Response::_422(Message::get('unable_to_update_profile'));
        }

        $userData = $user->resource('authUserFields');
        return Response::_200([
            'data' => $userData,
            'message' => Message::get('profile_updated_successfully'),
        ]);
    }
}
