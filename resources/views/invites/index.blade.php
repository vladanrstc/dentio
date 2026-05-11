@extends('layouts.app', ['title' => 'Tim i Invite'])

@section('content')
    <section class="grid cols-2">
        <article class="panel">
            <h3>Posalji invite</h3>
            <form method="POST" action="{{ route('team.invites.store') }}" class="form-grid">
                @csrf

                <label class="field full">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </label>

                <label class="field full">
                    <span>Uloga</span>
                    <select name="role" required>
                        <option value="dentist">Stomatolog</option>
                        <option value="nurse">Sestra</option>
                    </select>
                </label>

                <div class="field full">
                    <button class="btn" type="submit">Posalji pozivnicu</button>
                </div>
            </form>
        </article>

        <article class="panel">
            <h3>Osoblje kompanije</h3>
            @if($staff->isEmpty())
                <p class="muted">Nema unetog osoblja.</p>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Ime</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Uloga</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($staff as $member)
                        <tr>
                            <td>{{ $member->fullName() }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->phone ?: '-' }}</td>
                            <td>{{ $member->role }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>
    </section>

    <section class="panel section">
        <h3>Poslate pozivnice</h3>
        @if($invites->isEmpty())
            <p class="muted">Nema poslatih pozivnica.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Email</th>
                    <th>Uloga</th>
                    <th>Status</th>
                    <th>Istice</th>
                    <th>Kreirano</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invites as $invite)
                    <tr>
                        <td>{{ $invite->email }}</td>
                        <td>{{ $invite->role }}</td>
                        <td>
                            @if($invite->accepted_at)
                                <span class="tag">Prihvaceno</span>
                            @elseif($invite->expires_at?->isPast())
                                <span class="tag danger">Isteklo</span>
                            @else
                                <span class="tag warn">Ceka odgovor</span>
                            @endif
                        </td>
                        <td>{{ $invite->expires_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $invite->created_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="section">
                {{ $invites->links() }}
            </div>
        @endif
    </section>
@endsection

