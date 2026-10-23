@php
    $manageAlbums = $manageAlbums ?? false;
    $pickable = $albums->where('is_system', false)->values();
    $preselect = (isset($album) && $album && ! $album->is_system) ? $album->id : null;
@endphp

{{-- Watch --------------------------------------------------------------- --}}
{{-- .nd-dark: a film is watched with the lights down, whatever the time of day. --}}
<dialog class="vd-player nd-dark" id="vd-player-dialog" aria-label="Video player">
    <div class="vd-pl__head">
        <button type="button" class="nd-icon-btn" data-vd-close aria-label="Close player">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <h2 class="vd-pl__title" data-vd-pl-title dir="auto"></h2>
        <span class="vd-pl__meta">
            <span class="vd-badge" data-vd-pl-badge hidden>
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Gated
            </span>
            <span class="vd-pl__len" data-vd-pl-len></span>
        </span>
    </div>

    <div class="vd-pl__stage">
        <video class="vd-pl__video" data-vd-video controls playsinline preload="none" hidden></video>

        <div class="vd-pl__gate" data-vd-pl-gate hidden>
            <i class="fa-solid fa-lock vd-pl__gate-icon" aria-hidden="true"></i>
            <div>
                <h3 class="vd-pl__gate-title">This video is gated</h3>
                <p class="nd-hint">
                    The key isn't stored anywhere and isn't remembered between videos. Gating is access
                    control, not encryption — the file itself stays readable on the drive.
                </p>
            </div>
            <form data-vd-key-form data-turbo="false">
                <x-nd-secret class="nd-input" data-vd-key autocomplete="off"
                       placeholder="Key" aria-label="Key for this video" />
                <button type="submit" class="nd-btn nd-btn--primary">Play video</button>
            </form>
            <p class="nd-error" data-vd-key-error role="alert" hidden></p>
        </div>

        <div class="vd-pl__failed" data-vd-pl-failed hidden>
            <i class="fa-solid fa-link-slash" aria-hidden="true"></i>
            <div>
                <h3 class="vd-pl__gate-title">That didn't play</h3>
                <p class="nd-hint" data-vd-pl-failed-note></p>
            </div>
        </div>
    </div>

    <div class="vd-pl__foot">
        <p class="vd-pl__file" data-vd-pl-file dir="auto"></p>
        <span class="vd-pl__keys">
            <span><kbd class="nd-kbd">Space</kbd> play/pause</span>
            <span><kbd class="nd-kbd">←</kbd><kbd class="nd-kbd">→</kbd> 5s</span>
            <span><kbd class="nd-kbd">Esc</kbd> close</span>
        </span>
    </div>
</dialog>

@if ($canWrite)
{{-- Favourite ----------------------------------------------------------- --}}
{{-- No dialog, just the one form every star button shares. --}}
<form method="POST" action="" hidden data-vd-fav-form>
    @csrf
    @method('PATCH')
</form>

{{-- Register ------------------------------------------------------------ --}}
<dialog class="nd-dialog" id="vd-register-dialog" aria-labelledby="vd-register-title">
    <form method="POST" action="{{ route('videosPage') }}" data-vd-register-form>
        @csrf
        <div class="nd-dialog__head">
            <h2 class="nd-dialog__title" id="vd-register-title">Register a video</h2>
            <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-vd-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="nd-dialog__body">
            <div class="vd-form">
                <p class="nd-note nd-note--info">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>
                        The site never receives video uploads — a multi-GB file goes onto the drive
                        through Windows, and this registers the copy that is already there.
                        Everything is read from <code>{{ $folderLabel }}</code>.
                    </span>
                </p>

                <div class="nd-field">
                    <span class="nd-label" id="vd-file-label">File<span class="nd-req" aria-hidden="true">*</span></span>

                    @if (! $driveReady)
                        <p class="nd-hint">The drive isn't mounted, so nothing can be listed or registered right now.</p>
                    @elseif (count($available) === 0)
                        <p class="nd-hint">
                            Every video file in that folder already has an entry. Drop a new one in and
                            reload the page, or type a name below.
                        </p>
                    @else
                        <ul class="vd-files" role="list" data-vd-files aria-labelledby="vd-file-label">
                            @foreach ($available as $file)
                                <li>
                                    <button type="button" class="vd-file" data-vd-pick data-name="{{ $file['name'] }}" aria-pressed="false">
                                        <i class="fa-regular fa-file-video" aria-hidden="true"></i>
                                        <span class="vd-file__name" dir="auto">{{ $file['name'] }}</span>
                                        <span class="vd-file__size">{{ $file['size'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <label class="nd-check vd-manual">
                        <input type="checkbox" data-vd-manual @checked($errors->has('filename') || count($available) === 0 || ! $driveReady)>
                        <span>Type the file name instead</span>
                    </label>
                </div>

                <div class="nd-field" data-vd-manual-field @if (! ($errors->has('filename') || count($available) === 0 || ! $driveReady)) hidden @endif>
                    <label class="nd-label" for="vd-filename">File name, exactly as it is on the drive</label>
                    <input class="nd-input" id="vd-filename" name="filename" type="text" maxlength="255"
                           autocomplete="off" spellcheck="false" dir="auto"
                           value="{{ old('filename') }}"
                           aria-invalid="{{ $errors->has('filename') ? 'true' : 'false' }}"
                           placeholder="trip-to-karbala.mp4"
                           data-vd-filename>
                    @error('filename')
                        <p class="nd-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- What the picker chose, when the field above is collapsed. --}}
                <p class="vd-picked" data-vd-picked hidden>
                    <i class="fa-regular fa-file-video" aria-hidden="true"></i>
                    <span data-vd-picked-name dir="auto"></span>
                </p>

                <div class="nd-field">
                    <label class="nd-label" for="vd-title">Title<span class="nd-req" aria-hidden="true">*</span></label>
                    <input class="nd-input" id="vd-title" name="title" type="text" required maxlength="200"
                           autocomplete="off" dir="auto" value="{{ old('title') }}">
                    <p class="nd-hint">
                        The link is built from this once and never rebuilt, so a link already pasted
                        somewhere keeps working if the title changes later.
                    </p>
                </div>

                <div class="nd-field">
                    <label class="nd-label" for="vd-description">Description</label>
                    <textarea class="nd-input vd-textarea" id="vd-description" name="description"
                              rows="3" maxlength="2000" dir="auto">{{ old('description') }}</textarea>
                </div>

                <div class="nd-field">
                    <label class="nd-label" for="vd-album">Album</label>
                    <select class="nd-input" id="vd-album" name="album_id">
                        <option value="">No album</option>
                        @foreach ($pickable as $pick)
                            <option value="{{ $pick->id }}" @selected((int) old('album_id', $preselect) === $pick->id)>{{ $pick->name }}</option>
                        @endforeach
                    </select>
                    <p class="nd-hint">Length and a poster frame are read from the file when it is registered.</p>
                </div>
            </div>
        </div>

        <div class="nd-dialog__foot">
            <button type="button" class="nd-btn nd-btn--ghost" data-vd-close>Cancel</button>
            <button type="submit" class="nd-btn nd-btn--primary" data-vd-register-submit @disabled(! $driveReady)>Register</button>
        </div>
    </form>
</dialog>

{{-- Lock ---------------------------------------------------------------- --}}
<dialog class="nd-dialog nd-dialog--sm" id="vd-lock-dialog" aria-labelledby="vd-lock-title">
    <form method="POST" action="" data-vd-lock-form>
        @csrf
        @method('PATCH')
        <div class="nd-dialog__head">
            <h2 class="nd-dialog__title" id="vd-lock-title">Gate this video</h2>
            <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-vd-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="nd-dialog__body">
            <div class="nd-field">
                <label class="nd-label" for="vd-lock-key">Key<span class="nd-req" aria-hidden="true">*</span></label>
                <x-nd-secret class="nd-input" id="vd-lock-key" name="key" required minlength="8"
                       autocomplete="new-password" aria-describedby="vd-lock-hint" />
                <p class="nd-hint" id="vd-lock-hint">
                    At least 8 characters, and it is never stored — only a hash of it is. Gating is access
                    control, <strong>not</strong> encryption: the file stays readable on the drive and only
                    this site asks for the key. The poster is pixelated while it is gated.
                </p>
            </div>
        </div>
        <div class="nd-dialog__foot">
            <button type="button" class="nd-btn nd-btn--ghost" data-vd-close>Cancel</button>
            <button type="submit" class="nd-btn nd-btn--primary">Gate video</button>
        </div>
    </form>
</dialog>

{{-- Unlock -------------------------------------------------------------- --}}
<dialog class="nd-dialog nd-dialog--sm" id="vd-unlock-dialog" aria-labelledby="vd-unlock-title">
    <form method="POST" action="" data-vd-unlock-form>
        @csrf
        @method('PATCH')
        <div class="nd-dialog__head">
            <h2 class="nd-dialog__title" id="vd-unlock-title">Unlock this video</h2>
            <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-vd-close aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="nd-dialog__body">
            <div class="nd-field">
                <label class="nd-label" for="vd-unlock-key">Key<span class="nd-req" aria-hidden="true">*</span></label>
                <x-nd-secret class="nd-input" id="vd-unlock-key" name="key" required autocomplete="off" />
                <p class="nd-hint">The video goes back to being played by anyone signed in here.</p>
            </div>
        </div>
        <div class="nd-dialog__foot">
            <button type="button" class="nd-btn nd-btn--ghost" data-vd-close>Cancel</button>
            <button type="submit" class="nd-btn nd-btn--primary">Unlock</button>
        </div>
    </form>
</dialog>

{{-- Delete -------------------------------------------------------------- --}}
<dialog class="nd-dialog nd-dialog--sm" id="vd-delete-dialog" aria-labelledby="vd-delete-title" aria-describedby="vd-delete-note">
    <form method="POST" action="" data-vd-delete-form>
        @csrf
        @method('DELETE')
        <div class="nd-dialog__head">
            <h2 class="nd-dialog__title" id="vd-delete-title">Remove this video?</h2>
        </div>
        <div class="nd-dialog__body">
            <p class="vd-dialog-note" id="vd-delete-note">
                The entry and its cached poster go. <strong>The video file stays on the drive</strong> —
                it was put there by hand and this site has never owned it. A gated video needs no key
                to be removed; removing is not watching it.
            </p>
        </div>
        <div class="nd-dialog__foot">
            <button type="button" class="nd-btn nd-btn--ghost" data-vd-close autofocus>Cancel</button>
            <button type="submit" class="nd-btn nd-btn--danger">Remove entry</button>
        </div>
    </form>
</dialog>

@if ($manageAlbums)
    {{-- Album create / rename ------------------------------------------- --}}
    <dialog class="nd-dialog nd-dialog--sm" id="vd-album-dialog" aria-labelledby="vd-album-title">
        <form method="POST" action="{{ route('video-albums.store') }}" data-vd-album-form>
            @csrf
            <span data-vd-album-method></span>
            <div class="nd-dialog__head">
                <h2 class="nd-dialog__title" id="vd-album-title" data-vd-album-heading>New album</h2>
                <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-vd-close aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="nd-dialog__body">
                <div class="nd-field">
                    <label class="nd-label" for="vd-album-name">Name<span class="nd-req" aria-hidden="true">*</span></label>
                    <input class="nd-input" id="vd-album-name" name="name" type="text" required maxlength="120" autocomplete="off" dir="auto">
                </div>
            </div>
            <div class="nd-dialog__foot">
                <button type="button" class="nd-btn nd-btn--ghost" data-vd-close>Cancel</button>
                <button type="submit" class="nd-btn nd-btn--primary" data-vd-album-submit>Create album</button>
            </div>
        </form>
    </dialog>

    {{-- Album delete ---------------------------------------------------- --}}
    <dialog class="nd-dialog nd-dialog--sm" id="vd-album-delete-dialog" aria-labelledby="vd-album-delete-title">
        <form method="POST" action="" data-vd-album-delete-form>
            @csrf
            @method('DELETE')
            <div class="nd-dialog__head">
                <h2 class="nd-dialog__title" id="vd-album-delete-title">Delete this album?</h2>
            </div>
            <div class="nd-dialog__body">
                <p class="vd-dialog-note">
                    “<span data-vd-album-delete-name></span>” goes away. The videos in it stay —
                    an album is only a grouping.
                </p>
            </div>
            <div class="nd-dialog__foot">
                <button type="button" class="nd-btn nd-btn--ghost" data-vd-close autofocus>Cancel</button>
                <button type="submit" class="nd-btn nd-btn--danger">Delete album</button>
            </div>
        </form>
    </dialog>
@endif
@endif
