@extends('layouts.app', ['title' => 'Aktivacija naloga'])

@section('content')
    <section class="panel" style="max-width: 720px; margin: 20px auto;">
        <h2>Aktivacija naloga</h2>
        <p class="muted">Pozivnica za: <strong>{{ $invite->email }}</strong> (uloga: {{ $invite->role }})</p>
        <p class="muted">Vazi do {{ $invite->expires_at?->format('d.m.Y H:i') }}</p>

        <form method="POST" action="{{ route('invites.accept.store', $invite->token) }}" class="form-grid">
            @csrf
            <input type="hidden" name="requires_company" value="{{ $requiresCompany ? 1 : 0 }}">

            <label class="field">
                <span>Ime</span>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required>
            </label>

            <label class="field">
                <span>Prezime</span>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required>
            </label>

            <label class="field">
                <span>Telefon</span>
                <input type="text" name="phone" value="{{ old('phone') }}">
            </label>

            <label class="field">
                <span>Email (iz pozivnice)</span>
                <input type="email" value="{{ $invite->email }}" disabled>
            </label>

            <label class="field">
                <span>Lozinka</span>
                <input type="password" name="password" required>
            </label>

            <label class="field">
                <span>Potvrda lozinke</span>
                <input type="password" name="password_confirmation" required>
            </label>

            @if($requiresCompany)
                <label class="field">
                    <span>Naziv kompanije</span>
                    <input type="text" name="company_name" value="{{ old('company_name') }}" required>
                </label>

                <label class="field">
                    <span>Adresa kompanije</span>
                    <input type="text" name="company_address" value="{{ old('company_address') }}" required>
                </label>

                <label class="field full">
                    <span>Telefon kompanije</span>
                    <input type="text" name="company_phone" value="{{ old('company_phone') }}">
                </label>
            @endif

            <div class="field full">
                <button class="btn" type="submit">Aktiviraj nalog</button>
            </div>
        </form>
    </section>
@endsection

