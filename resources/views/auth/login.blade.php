@extends('layouts.app', ['title' => 'Prijava'])

@section('content')
    <section class="panel auth-card">
        <h2>Prijava</h2>
        <p class="muted">Prijavite se na Dentio nalog.</p>

        <form method="POST" action="{{ route('login.store') }}" class="grid">
            @csrf
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>

            <label class="field">
                <span>Lozinka</span>
                <input type="password" name="password" required>
            </label>

            <button type="submit" class="btn">Uloguj se</button>
        </form>
    </section>
@endsection

