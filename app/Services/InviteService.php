<?php

namespace App\Services;

use Carbon\Carbon;

use App\Models\Role;
use App\Mail\InviteMail;
use App\Models\InviteRole;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Repositories\StaffRepository;
use App\Repositories\InviteRepository;
use App\Repositories\StudentRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\ProgramRepository;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTFactory;
use Illuminate\Validation\ValidationException;


class InviteService
{

    private $staffRepository, $roleRepository, $studentRepository, $departmentRepository, $programRepository;

    public function __construct(RoleRepository $roleRepository, StaffRepository $staffRepository, 
        StudentRepository $studentRepository, DepartmentRepository $departmentRepository, ProgramRepository $programRepository)
    {
        $this->roleRepository = $roleRepository;
        $this->staffRepository = $staffRepository;
        $this->studentRepository = $studentRepository;
        $this->departmentRepository = $departmentRepository;
        $this->programRepository = $programRepository;
    }

    public function repo()
    {
        return app(InviteRepository::class);
    }

    public function create($opts)
    {
        $invite = $this->repo()->create($opts);

        if (isset($opts['role_id'])) {
            $role_id = $opts['role_id'];
            $invite->roles()->create([
                'role_id' => $role_id,
            ]);
        } else if (isset($opts['roles'])) {
            $roleIds = new Collection($opts['roles']);
            $roleIds->map(function ($data) use ($invite) {
                $invite->roles()->create([
                    'role_id' => $data['role_id'],
                    'extras' => isset($data['extras']) ? $data['extras'] : null,
                ]);
            });
        } else {
            throw ValidationException::withMessages([
                'role_id' => 'should exist if "roles" are absent"',
                'roles' => 'should exist if "role_id" is absent"',
            ]);
        }
        $invite->load('roles');
        $this->sendMail($invite);
        return $invite;
    }

    public function resolve($id)
    {
        $invite = $this->repo()->single($id);
        $roles = $invite->roles()->get();
        $user = auth()->user();
        $school_id = $invite->school_id;

        foreach ($roles as $_role) {
            $role_id = $_role->role_id;
            $extras = json_decode($_role->extras);
            $role = $this->roleRepository->single($role_id);

            if ($role->name == Role::ADMIN) {
                $admin = $user->roles()->attach([
                    $role->id => [],
                ]);
            }

            if ($role->name == Role::SCHOOL_OWNER) {
                $staffRole = Role::whereName(Role::STAFF)->first();

                $user->roles()->attach([
                    $role->id => [
                        'school_id' => $school_id,
                    ],
                    $staffRole->id => [ //create staff role
                        'school_id' => $school_id,
                    ],
                ]);

                //add as staff
                $department_id = isset($extras->department_id) ? $extras->department_id : null;
                $opts = ['user_id' => $user->id, 'school_id' => $school_id, 'title' => ''];
                $staff = $this->staffRepository->create($opts);
                
                
                if ($department_id) {
                    $department = $this->departmentRepository->department($department_id);
                    if ($department->school()->first()->id == $school_id) {
                        $opts = ['department_id' => $department_id];
                        $staff = $this->staffRepository->update($staff->id, $opts);
                    }
                }

                $user['staff'] = $staff;
            }

            if ($role->name == Role::STAFF) {
                $user->roles()->attach([
                    $role->id => [
                        'school_id' => $school_id,
                    ],
                ]);

                //create staff record
                $department_id = isset($extras->department_id) ? $extras->department_id : null;
                $opts = ['user_id' => $user->id, 'school_id' => $school_id, 'title' => ''];
                $staff = $this->staffRepository->create($opts);

                if ($department_id) {
                    $department = $this->departmentRepository->department($department_id);
                    if ($department->school()->first()->id == $school_id) {
                        $opts = ['department_id' => $department_id];
                        $staff = $this->staffRepository->update($staff->id, $opts);
                    }
                }
                $user['staff'] = $staff;

            }

            if ($role->name == Role::STUDENT) {
                $user->roles()->attach([
                    $role->id => [
                        'school_id' => $school_id,
                    ],
                ]);

                //create student record
                $program_id = $extras ? $extras->program_id : null;
                $matric_no = $extras ? $extras->matric_no : null;
                $opts = ['user_id' => $user->id, 'matric_no' => $matric_no, 'program_id' => $program_id];
                $student = $this->studentRepository->create($opts);

                $user['student'] = $student;
            }

        }

        $invite->delete();

        return $user;
    }

    /*
     *sends new invite mail
     *
     */
    public function sendMail($invite)
    {
        $id = $invite['id'];
        $email = $invite['email'];
        $message = $invite['message'];

        //create jwt with aud to prevent interferance with authentication
        $payload = JWTFactory::sub($id)->aud('invite')->make();
        $token = JWTAuth::encode($payload)->get();
        $link = url("/invites/{$token}");

        return Mail::to($email)->send(new InviteMail(['message' => $message, 'link' => $link]));
    }
}
