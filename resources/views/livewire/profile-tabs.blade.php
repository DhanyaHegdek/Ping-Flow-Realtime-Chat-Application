<div style="width:100%">
    <div class="profile-tabs">
        <button class="profile-tab {{ $tab === 'info' ? 'active' : '' }}"
                wire:click="switchTab('info')">Info</button>
        <button class="profile-tab {{ $tab === 'media' ? 'active' : '' }}"
                wire:click="switchTab('media')">Media</button>
    </div>

    @if($tab === 'info')
        <div class="profile-tab-content">
            <div class="profile-section-title">Conversation Info</div>
            <div class="profile-stat">
                <span class="profile-stat-label">Started</span>
                <span class="profile-stat-val">
                    {{ \Carbon\Carbon::parse($conversation->created_at)->format('d M Y') }}
                </span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-label">Last message</span>
                <span class="profile-stat-val">
                    {{ $conversation->last_message_at
                        ? \Carbon\Carbon::parse($conversation->last_message_at)->format('d M')
                        : 'Never' }}
                </span>
            </div>
        </div>
    @endif

    @if($tab === 'media')
        <div class="profile-tab-content">
            @if($loadingFiles)
                <p class="media-empty">Loading...</p>
            @elseif(count($files) === 0)
                <p class="media-empty">No files shared yet</p>
            @else
                @if(count($images) > 0)
                    <div class="profile-section-title">Photos ({{ count($images) }})</div>
                    <div class="media-grid">
                        @foreach($images as $f)
                            <a href="{{ asset('storage/' . $f['file_path']) }}"
                               target="_blank" class="media-thumb">
                                <img src="{{ asset('storage/' . $f['file_path']) }}"
                                     alt="{{ $f['file_name'] }}" />
                            </a>
                        @endforeach
                    </div>
                @endif

                @if(count($docs) > 0)
                    <div style="margin-top:16px">
                        <div class="profile-section-title">Files ({{ count($docs) }})</div>
                        <div class="media-files-list">
                            @foreach($docs as $f)
                                <a href="{{ asset('storage/' . $f['file_path']) }}"
                                   target="_blank"
                                   download="{{ $f['file_name'] }}"
                                   class="media-file-row">
                                    <span class="media-file-icon">📄</span>
                                    <div class="media-file-info">
                                        <div class="media-file-name">{{ $f['file_name'] }}</div>
                                        <div class="media-file-meta">
                                            {{ $f['sender']['name'] ?? '' }}
                                            &bull;
                                            {{ \Carbon\Carbon::parse($f['created_at'])->format('d M') }}
                                        </div>
                                    </div>
                                    {{-- Uses Message::getFileSizeFormattedAttribute() --}}
                                    <span class="media-file-size">{{ $f['file_size_fmt'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
