<?php

return [
    'accepted' => 'Polje :attribute mora biti prihvaceno.',
    'after' => 'Polje :attribute mora biti datum posle :date.',
    'after_or_equal' => 'Polje :attribute mora biti datum posle ili jednak :date.',
    'before' => 'Polje :attribute mora biti datum pre :date.',
    'before_or_equal' => 'Polje :attribute mora biti datum pre ili jednak :date.',
    'confirmed' => 'Potvrda za polje :attribute se ne poklapa.',
    'date' => 'Polje :attribute mora biti ispravan datum.',
    'email' => 'Polje :attribute mora biti ispravna email adresa.',
    'exists' => 'Izabrana vrednost za polje :attribute nije ispravna.',
    'in' => 'Izabrana vrednost za polje :attribute nije ispravna.',
    'integer' => 'Polje :attribute mora biti ceo broj.',
    'max' => [
        'numeric' => 'Polje :attribute ne sme biti vece od :max.',
        'string' => 'Polje :attribute ne sme imati vise od :max karaktera.',
    ],
    'min' => [
        'numeric' => 'Polje :attribute mora biti najmanje :min.',
        'string' => 'Polje :attribute mora imati najmanje :min karaktera.',
    ],
    'required' => 'Polje :attribute je obavezno.',
    'string' => 'Polje :attribute mora biti tekst.',
    'unique' => 'Polje :attribute je vec zauzeto.',

    'custom' => [
        'ends_at' => [
            'after' => 'Kraj termina mora biti posle početka termina.',
        ],
    ],

    'attributes' => [
        'starts_at' => 'početak termina',
        'ends_at' => 'kraj termina',
        'cancel_reason' => 'razlog otkazivanja',
        'appointment' => 'termin',
        'appointment_id' => 'termin',
        'patient' => 'pacijent',
        'patient_id' => 'pacijent',
        'company' => 'kompanija',
        'company_id' => 'kompanija',
        'invite' => 'pozivnica',
        'role' => 'uloga',
    ],
];
