<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * @var UserService
     */
    protected UserService $userService;

    /**
     * UserController constructor.
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of users.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        try {
            $users = User::paginate(15);

            return view('users.index', compact('users'));
        } catch (\Exception $e) {
            Log::error('Error al listar usuarios: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar los usuarios.');
        }
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create');
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        try {
            $this->userService->create($request->validated());

            return redirect()->route('users.index')
                ->with('success', 'Usuario creado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al crear usuario: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear el usuario. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): View
    {
        $this->authorize('view', $user);

        try {
            return view('users.show', compact('user'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar usuario: ' . $e->getMessage());

            return redirect()->route('users.index')
                ->with('error', 'Ocurrió un error al cargar el usuario.');
        }
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        try {
            $this->userService->update($user->id, $request->validated());

            return redirect()->route('users.index')
                ->with('success', 'Usuario actualizado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar usuario: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al actualizar el usuario. Por favor intente nuevamente.');
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->userService->delete($user->id);

            return redirect()->route('users.index')
                ->with('success', 'Usuario eliminado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar usuario: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar el usuario. Por favor intente nuevamente.');
        }
    }
}
