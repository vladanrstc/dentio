<form method="GET" action="{{ route('patients.index') }}">
    <div class="actions">
        <input type="text" name="search" value="{{ $search }}" placeholder="Ime, prezime ili telefon">
        <button class="btn" type="submit">Pretrazi</button>
        @if($search !== '')
            <a href="{{ route('patients.index') }}" class="btn btn-secondary">Reset</a>
        @endif
    </div>
</form>
