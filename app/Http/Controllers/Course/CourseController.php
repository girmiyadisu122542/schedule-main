<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\CourseRequest;
use App\Models\Catalogue\Course;
use App\Services\Catalogue\CourseService;
use Helper\Response\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Translation\Message;

class CourseController extends Controller {

    /**
     * List courses with search and filters.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse {
        if (!$this->userCanSeeCourse() && !isDropdownEnabled()) {
            return Response::_403();
        }

        $search = $request->input('search');
        $isActive = $request->input('is_active');

        $courses = Course::query()
            ->with(['department', 'courseType', 'user'])
            ->when($search, function ($query) use ($search) {
                $query
                    ->where(function ($query) use ($search) {
                        $query
                            ->where('code', 'ilike', "%{$search}%")
                            ->orWhere(fn ($query) => $query->jsonbLangValueSearch('title', $search, true));
                    });
            })
            ->when($request->input('department_id'), fn ($query) => $query->where('department_id', (int) $request->input('department_id')))
            ->when($request->input('course_type_lookup_value_id'), fn ($query) => $query->where('course_type_lookup_value_id', (int) $request->input('course_type_lookup_value_id')))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)))
            ->latest('updated_at')
            ->paginate(static::getPerPage());

        return Response::_200([
            'data' => $courses->collection(isDropdownEnabled() ? 'idAndNameFields' : null),
            'pagination' => Course::extractPagination($courses),
        ]);
    }

    /**
     * Show a course by numeric id OR uuid — see CLAUDE Sec. 10.18.
     *
     * @param string $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($key): JsonResponse {
        if (!$this->userCanSeeCourse()) {
            return Response::_403();
        }

        $course = Course::query()
            ->with(['department', 'courseType', 'user'])
            ->when(ctype_digit((string) $key), fn ($query) => $query->where('id', (int) $key))
            ->when(!ctype_digit((string) $key), fn ($query) => $query->where('uuid', $key))
            ->first();

        if (!$course) {
            return Response::_404(Message::get('course_not_found'));
        }

        return Response::_200([
            'data' => $course->resource(),
        ]);
    }

    /**
     * Create a course.
     *
     * @param \App\Http\Requests\Course\CourseRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CourseRequest $request): JsonResponse {
        try {
            $result = app(CourseService::class)->createCourse($request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_create_course'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->title__localized];

        return Response::_201([
            'data' => $result->resource(),
            'message' => Message::get('course_created_successfully', $bindings),
        ]);
    }

    /**
     * Update a course.
     *
     * @param \App\Http\Requests\Course\CourseRequest $request
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CourseRequest $request, $id): JsonResponse {
        $course = Course::find($id);
        if (!$course) {
            return Response::_404(Message::get('course_not_found'));
        }

        try {
            $result = app(CourseService::class)->updateCourse($course, $request->validated());
        } catch (\Exception $exception) {
            return Response::_500(Message::get('unable_to_update_course'));
        }

        if (is_string($result)) {
            return Response::_422(Message::get($result));
        }

        $bindings = ['name' => $result->title__localized];

        return Response::_200([
            'data' => $result->resource(),
            'message' => Message::get('course_updated_successfully', $bindings),
        ]);
    }

    /**
     * Delete a course.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse {
        if (!$this->userCanDeleteCourse()) {
            return Response::_403();
        }

        $course = Course::find($id);
        if (!$course) {
            return Response::_404(Message::get('course_not_found'));
        }

        $bindings = ['name' => $course->title__localized];

        try {
            $course->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return Response::_422(Message::get('course_is_in_use'));
        }

        return Response::_200([
            'message' => Message::get('course_deleted_successfully', $bindings),
        ]);
    }

    /**
     * Toggle a course is_active flag.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState($id): JsonResponse {
        if (!$this->userCanChangeCourseState()) {
            return Response::_403();
        }

        $course = Course::find($id);
        if (!$course) {
            return Response::_404(Message::get('course_not_found'));
        }

        $validator = Validator::make(request()->all(), [
            'is_active' => ['required', 'boolean'],
        ], Message::get('course') ?? []);

        if (!$validator->passes()) {
            return Response::_422(null, $validator->errors());
        }

        $isActive = request()->boolean('is_active');
        if ($course->is_active === $isActive) {
            return Response::_422(Message::get('nothing_is_changed'));
        }

        $course->is_active = $isActive;
        $course->save();

        $message = $isActive
            ? 'course_activated'
            : 'course_deactivated';

        return Response::_200([
            'data' => $course->resource(),
            'message' => Message::get($message, ['name' => $course->title__localized]),
        ]);
    }
}
