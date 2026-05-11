@extends('layouts.app', ['title' => 'Pacijent'])

@section('content')
    @php
        $interventions = $patient->interventions->sortByDesc('intervention_date');
        $appointments = $patient->appointments->sortByDesc('starts_at');
        $tasks = $patient->tasks->sortByDesc('created_at');
        $statusLogs = $patient->statusLogs->sortByDesc('created_at');
        $totalCost = (float) $interventions->sum('total_cost');
        $paidAmount = (float) $interventions->sum('paid_amount');
        $outstandingAmount = max(0, $totalCost - $paidAmount);
    @endphp

    <section class="panel">
        <div style="display:flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0 0 4px;">{{ $patient->fullName() }}</h2>
                <div class="muted">{{ $patient->phone ?: '-' }} | {{ $patient->email ?: 'bez emaila' }}</div>
            </div>
            <div class="actions">
                <a href="{{ route('patients.index') }}" class="btn btn-secondary">Nazad na listu</a>
            </div>
        </div>

        <div class="actions" style="margin-top: 10px;">
            <span class="tag">Status: {{ $patient->manual_status }}</span>
            @if($patient->open_tasks_count > 0)
                <span class="tag warn">Aktivne stavke: {{ $patient->open_tasks_count }}</span>
            @else
                <span class="tag">Bez aktivnih stavki</span>
            @endif
            <span class="tag">Dug: {{ number_format($outstandingAmount, 2) }} RSD</span>
        </div>
    </section>

    <section class="grid cols-2" style="margin-top: 12px;">
        <article class="panel">
            <h3>Podaci o pacijentu</h3>
            <form method="POST" action="{{ route('patients.update', $patient->id) }}" class="form-grid">
                @csrf
                @method('PUT')

                <label class="field">
                    <span>Ime</span>
                    <input type="text" name="first_name" value="{{ old('first_name', $patient->first_name) }}" required>
                </label>

                <label class="field">
                    <span>Prezime</span>
                    <input type="text" name="last_name" value="{{ old('last_name', $patient->last_name) }}" required>
                </label>

                <label class="field">
                    <span>Telefon</span>
                    <input type="text" name="phone" value="{{ old('phone', $patient->phone) }}" required>
                </label>

                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $patient->email) }}">
                </label>

                <label class="field full">
                    <span>Adresa</span>
                    <input type="text" name="address" value="{{ old('address', $patient->address) }}" required>
                </label>

                <label class="field full">
                    <span>Primarni stomatolog</span>
                    <select name="primary_dentist_id">
                        <option value="">-- nije dodeljen --</option>
                        @foreach($dentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) old('primary_dentist_id', $patient->primary_dentist_id) === (string) $dentist->id)>
                                {{ $dentist->fullName() }} ({{ $dentist->role }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field full">
                    <span>Napomena</span>
                    <textarea name="notes">{{ old('notes', $patient->notes) }}</textarea>
                </label>

                <div class="field full">
                    <button class="btn" type="submit">Sacuvaj izmene</button>
                </div>
            </form>
        </article>

        <article class="panel">
            <h3>Rucna promena statusa</h3>
            <form method="POST" action="{{ route('patients.status.update', $patient->id) }}" class="grid">
                @csrf
                @method('PATCH')

                <label class="field">
                    <span>Status</span>
                    <select name="manual_status" required>
                        @foreach(['active' => 'active', 'inactive' => 'inactive', 'transferred' => 'transferred', 'completed' => 'completed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('manual_status', $patient->manual_status) === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span>Obrazlozenje</span>
                    <textarea name="manual_status_reason">{{ old('manual_status_reason', $patient->manual_status_reason) }}</textarea>
                </label>

                <button class="btn" type="submit">Promeni status</button>
            </form>

            <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 16px 0;">

            <h3>Dodaj aktivnu stavku</h3>
            <form method="POST" action="{{ route('patients.tasks.store', $patient->id) }}" class="form-grid">
                @csrf

                <label class="field full">
                    <span>Opis stavke</span>
                    <textarea name="description" required>{{ old('description') }}</textarea>
                </label>

                <label class="field">
                    <span>Rok</span>
                    <input type="date" name="due_date" value="{{ old('due_date') }}">
                </label>

                <label class="field">
                    <span>Dodeljeno</span>
                    <select name="assigned_to_user_id">
                        <option value="">-- nije dodeljeno --</option>
                        @foreach($dentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) old('assigned_to_user_id') === (string) $dentist->id)>
                                {{ $dentist->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="field full">
                    <button class="btn" type="submit">Dodaj stavku</button>
                </div>
            </form>
        </article>
    </section>

    <section class="grid cols-2" style="margin-top: 12px;">
        <article class="panel">
            <h3>Novi termin</h3>
            <form method="POST" action="{{ route('patients.appointments.store', $patient->id) }}" class="form-grid">
                @csrf

                <label class="field">
                    <span>Pocetak</span>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>
                </label>

                <label class="field">
                    <span>Kraj</span>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
                </label>

                <label class="field">
                    <span>Tip termina</span>
                    <select name="type" required>
                        @foreach(['checkup' => 'checkup', 'intervention' => 'intervention', 'control' => 'control'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span>Odgovorni lekar</span>
                    <select name="assigned_user_id">
                        <option value="">-- nije dodeljeno --</option>
                        @foreach($dentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) old('assigned_user_id') === (string) $dentist->id)>
                                {{ $dentist->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field full">
                    <span>Napomena</span>
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </label>

                <label class="field">
                    <span>Podsetnik osoblju</span>
                    <input type="datetime-local" name="reminder_staff_at" value="{{ old('reminder_staff_at') }}">
                </label>

                <label class="field">
                    <span>Podsetnik pacijentu</span>
                    <input type="datetime-local" name="reminder_patient_at" value="{{ old('reminder_patient_at') }}">
                </label>

                <div class="field full">
                    <button class="btn" type="submit">Zakazi termin</button>
                </div>
            </form>
        </article>

        <article class="panel">
            <h3>Nova intervencija</h3>
            <form method="POST" action="{{ route('patients.interventions.store', $patient->id) }}" class="form-grid">
                @csrf

                <label class="field full">
                    <span>Naziv intervencije</span>
                    <input type="text" name="title" value="{{ old('title') }}" required>
                </label>

                <label class="field">
                    <span>Datum</span>
                    <input type="date" name="intervention_date" value="{{ old('intervention_date', now()->format('Y-m-d')) }}" required>
                </label>

                <label class="field">
                    <span>Izvrsio lekar</span>
                    <select name="performed_by_user_id">
                        <option value="">-- automatski prijavljen korisnik --</option>
                        @foreach($dentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) old('performed_by_user_id') === (string) $dentist->id)>
                                {{ $dentist->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field full">
                    <span>Povezan termin (opciono)</span>
                    <select name="appointment_id">
                        <option value="">-- bez povezanog termina --</option>
                        @foreach($appointments as $appointment)
                            <option value="{{ $appointment->id }}">
                                #{{ $appointment->id }} {{ $appointment->starts_at?->format('d.m.Y H:i') }} ({{ $appointment->type }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field full">
                    <span>Opis uradjenog</span>
                    <textarea name="description">{{ old('description') }}</textarea>
                </label>

                <label class="field full">
                    <span>Sledeci korak</span>
                    <textarea name="next_step">{{ old('next_step') }}</textarea>
                </label>

                <label class="field">
                    <span>Dodeli naredni korak</span>
                    <select name="assigned_to_user_id">
                        <option value="">-- automatski primarni lekar --</option>
                        @foreach($dentists as $dentist)
                            <option value="{{ $dentist->id }}" @selected((string) old('assigned_to_user_id') === (string) $dentist->id)>
                                {{ $dentist->fullName() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span>Rok za naredni korak</span>
                    <input type="date" name="task_due_date" value="{{ old('task_due_date') }}">
                </label>

                <label class="field">
                    <span>Cena intervencije (RSD)</span>
                    <input type="number" step="0.01" min="0" name="total_cost" value="{{ old('total_cost', '0') }}">
                </label>

                <label class="field">
                    <span>Placeno (RSD)</span>
                    <input type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', '0') }}">
                </label>

                <label class="field">
                    <span>Podsetnik osoblju</span>
                    <input type="datetime-local" name="reminder_staff_at" value="{{ old('reminder_staff_at') }}">
                </label>

                <label class="field">
                    <span>Podsetnik pacijentu</span>
                    <input type="datetime-local" name="reminder_patient_at" value="{{ old('reminder_patient_at') }}">
                </label>

                <div class="field full">
                    <button class="btn" type="submit">Sacuvaj intervenciju</button>
                </div>
            </form>
        </article>
    </section>

    <section class="grid cols-2" style="margin-top: 12px;">
        <article class="panel">
            <h3>Aktivne i zavrsene stavke</h3>
            @if($tasks->isEmpty())
                <p class="muted">Nema unetih stavki.</p>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Opis</th>
                        <th>Rok</th>
                        <th>Status</th>
                        <th>Dodeljeno</th>
                        <th>Akcija</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($tasks as $task)
                        <tr>
                            <td>{{ $task->description }}</td>
                            <td>{{ $task->due_date?->format('d.m.Y') ?: '-' }}</td>
                            <td>{{ $task->status }}</td>
                            <td>{{ $task->assignedTo?->fullName() ?? '-' }}</td>
                            <td>
                                @if($task->status === 'open')
                                    <form method="POST" action="{{ route('patients.tasks.complete', [$patient->id, $task->id]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-secondary">Oznaci zavrseno</button>
                                    </form>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>

        <article class="panel">
            <h3>Status log</h3>
            @if($statusLogs->isEmpty())
                <p class="muted">Nema promena statusa.</p>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Vreme</th>
                        <th>Promena</th>
                        <th>Ko</th>
                        <th>Razlog</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($statusLogs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $log->previous_status }} -> {{ $log->new_status }}</td>
                            <td>{{ $log->changedBy?->fullName() ?? '-' }}</td>
                            <td>{{ $log->reason ?: '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>
    </section>

    <section class="grid cols-2" style="margin-top: 12px;">
        <article class="panel">
            <h3>Istorija termina</h3>
            @if($appointments->isEmpty())
                <p class="muted">Nema zakazanih termina.</p>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Vreme</th>
                        <th>Tip</th>
                        <th>Status</th>
                        <th>Zakazao</th>
                        <th>Lekar</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->starts_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $appointment->type }}</td>
                            <td>{{ $appointment->status }}</td>
                            <td>{{ $appointment->scheduledBy?->fullName() ?? '-' }}</td>
                            <td>{{ $appointment->assignedTo?->fullName() ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>

        <article class="panel">
            <h3>Istorija intervencija i finansije</h3>
            @if($interventions->isEmpty())
                <p class="muted">Nema zabelezenih intervencija.</p>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Intervencija</th>
                        <th>Lekar</th>
                        <th>Cena</th>
                        <th>Placeno</th>
                        <th>Razlika</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($interventions as $intervention)
                        <tr>
                            <td>{{ $intervention->intervention_date?->format('d.m.Y') }}</td>
                            <td>
                                <strong>{{ $intervention->title }}</strong><br>
                                <span class="muted">{{ $intervention->next_step ?: 'bez sledeceg koraka' }}</span>
                            </td>
                            <td>{{ $intervention->performedBy?->fullName() ?? '-' }}</td>
                            <td>{{ number_format((float) $intervention->total_cost, 2) }}</td>
                            <td>{{ number_format((float) $intervention->paid_amount, 2) }}</td>
                            <td>{{ number_format(max(0, (float) $intervention->total_cost - (float) $intervention->paid_amount), 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="3">Ukupno</th>
                        <th>{{ number_format($totalCost, 2) }}</th>
                        <th>{{ number_format($paidAmount, 2) }}</th>
                        <th>{{ number_format($outstandingAmount, 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            @endif
        </article>
    </section>
@endsection
