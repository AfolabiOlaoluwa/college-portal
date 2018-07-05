<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\StaffTeachCourseService;
use App\Filters\StaffTeachCourseFilters;
use App\Http\Requests\StaffTeachCourseRequest;

class StaffTeachCourseController extends ApiController
{
    protected $service;

    public function __construct(StaffTeachCourseService $service) {
        $this->service = $service;
    }

    public function service() {
        return $this->service;
    }

    /**
     * Get Staff-Teach-Course Info by ID
     */
    public function show(Request $request, StaffTeachCourseFilters $filters, $id) {
        $staffCourse = $this->service()->repo()->single($id, $filters);
        $this->authorize('view', $staffCourse); /** ensure the current user has view rights */
        return $staffCourse;
    }

    /**
     * Get Staff-Teach-Course List
     */
    public function index(Request $request, StaffTeachCourseFilters $filters) {
        $staffCourses = $this->service()->repo()->list($request->user(), $filters);
        return $staffCourses;
    }

    /**
     * Delete Staff-Teach-Course
     */
    public function destroy(Request $request, $id) {
        $staffCourse = $this->service()->repo()->single($id);
        $this->authorize('delete', $staffCourse); /** ensure the current user has delete rights */
        $this->service()->repo()->delete($id);
        return $this->ok();
    }

    /**
     * Create Staff-Teach-Course
     */
    public function store(StaffTeachCourseRequest $request) {
        $staffTeachCourse = $this->service()->repo()->create($request->all());
        return $this->json($staffTeachCourse);
    }

    /**
     * Update Staff-Teach-Course
     */
    public function update(Request $request, $id) {
        $staffTeachCourse = $this->service()->repo()->single($id);
        $this->authorize('update', $staffTeachCourse);
        $staffTeachCourse = $this->service()->repo()->update($id, $request->all());
        return $this->json($staffTeachCourse);
    }
}
