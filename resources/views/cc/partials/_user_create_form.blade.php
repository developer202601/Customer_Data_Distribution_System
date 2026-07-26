{{--
    Shared user-create form partial.

    Expected variables:
      $action      — form POST action URL
      $mode        — 'rtom' | 'caller' | 'default'  (controls which extra fields appear)
      $rtoms       — collection of RTOM strings (used when $mode === 'rtom')
      $isSupervisor — bool, unused currently but kept for compatibility
--}}

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ $action }}" method="post" style="max-width:480px;">
    @csrf

    <div class="mb-3">
        <label class="form-label" for="ucf_username">Username <span class="text-muted small">(6 digits)</span></label>
        <input id="ucf_username" name="username" type="text" inputmode="numeric"
               class="form-control @error('username') is-invalid @enderror"
               maxlength="6" pattern="\d{6}" required
               value="{{ old('username') }}" placeholder="e.g. 123456">
        @error('username')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="ucf_name">Name <span class="text-muted small">(optional)</span></label>
        <input id="ucf_name" name="name" type="text"
               class="form-control @error('name') is-invalid @enderror"
               maxlength="45" value="{{ old('name') }}">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if(($mode ?? '') === 'rtom')
        <div class="mb-3">
            <label class="form-label" for="ucf_rtom">RTOM</label>
            @if(!empty($rtoms) && $rtoms->isNotEmpty())
                <select id="ucf_rtom" name="rtom"
                        class="form-select @error('rtom') is-invalid @enderror" required>
                    <option value="">— Select RTOM —</option>
                    @foreach($rtoms as $rtom)
                        <option value="{{ $rtom }}" {{ old('rtom') === $rtom ? 'selected' : '' }}>
                            {{ $rtom }}
                        </option>
                    @endforeach
                </select>
            @else
                <input id="ucf_rtom" name="rtom" type="text"
                       class="form-control @error('rtom') is-invalid @enderror"
                       maxlength="100" required value="{{ old('rtom') }}"
                       placeholder="Enter RTOM name">
            @endif
            @error('rtom')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-success rounded-pill px-4">Create</button>
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
    </div>
</form>
