@extends('layouts.app')

@section('content')
<div class="container-fluid pt-4">
    <div class="row g-4">
        <!-- New Channel Form -->
        <div class="col-md-4">
            <div class="df-panel p-4 h-100">
                <div class="d-flex align-items-center gap-2 mb-4">
                    <div class="p-2 bg-primary rounded bg-opacity-10 text-primary">
                        <i class="bi bi-plus-circle-fill"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">Add New Group</h5>
                </div>
                
                <form action="{{ route('admin.telegram.channels.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Group Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Experimental VIP Group" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Chat ID</label>
                        <input type="text" name="chat_id" class="form-control" placeholder="-100123456789" required>
                        <div class="form-text small">Use <code class="text-primary">/id</code> command if finder is active.</div>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check_input" type="checkbox" name="is_active" value="1" id="isActive" checked>
                        <label class="form-check-label small" for="isActive">Enable Routing</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-save"></i> Save Group
                    </button>
                </form>
            </div>
        </div>

        <!-- Channel List & Routing -->
        <div class="col-md-8">
            <div class="df-panel p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 fw-bold">Active Routing Channels</h5>
                    <span class="badge bg-light text-dark border">{{ $channels->count() }} Groups Connected</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light border-0">
                            <tr>
                                <th class="py-3 px-4 small text-uppercase text-muted border-0">Group Info</th>
                                <th class="py-3 px-4 small text-uppercase text-muted border-0">Linked Strategies</th>
                                <th class="py-3 px-4 small text-uppercase text-muted border-0 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody class="border-0">
                            @foreach($channels as $channel)
                            <tr class="border-bottom">
                                <td class="py-4 px-4">
                                    <div class="fw-bold mb-0">{{ $channel->name }}</div>
                                    <code class="small text-muted">{{ $channel->chat_id }}</code>
                                    @if($channel->is_active)
                                        <span class="badge bg-success-subtle text-success ms-1" style="font-size: 0.65rem;">ACTIVE</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4">
                                    <div class="d-flex flex-wrap gap-1">
                                        @forelse($channel->methods as $m)
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.75rem;">
                                                #{{ $m->id }} {{ $m->nama_metode }}
                                            </span>
                                        @empty
                                            <span class="small text-muted italic">No methods linked</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <!-- Sync Button (Modal Trigger) -->
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sync{{ $channel->id }}">
                                            <i class="bi bi-link-45deg"></i> Link
                                        </button>
                                        <!-- Delete -->
                                        <form action="{{ route('admin.telegram.channels.delete', $channel) }}" method="POST" onsubmit="return confirm('Archive this group?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Link Modal -->
                                    <div class="modal fade" id="sync{{ $channel->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg">
                                                <form action="{{ route('admin.telegram.channels.sync', $channel) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Link Strategies to {{ $channel->name }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body py-4 text-start">
                                                        <p class="small text-muted mb-3">Signals from selected strategies will be routed to this group.</p>
                                                        <div class="row g-2 overflow-auto" style="max-height: 300px;">
                                                            @foreach($methods as $method)
                                                            <div class="col-12">
                                                                <div class="form-check p-2 rounded-3 border bg-light-subtle hover-bg-light">
                                                                    <input class="form-check-input ms-0" type="checkbox" name="method_ids[]" value="{{ $method->id }}" 
                                                                           id="m{{ $channel->id }}_{{ $method->id }}"
                                                                           {{ $channel->methods->contains($method->id) ? 'checked' : '' }}>
                                                                    <label class="form-check-label d-flex justify-content-between align-items-center flex-grow-1" for="m{{ $channel->id }}_{{ $method->id }}">
                                                                        <span class="ms-1">{{ $method->nama_metode }}</span>
                                                                        <span class="small text-muted">ID: {{ $method->id }}</span>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary px-4">Save Routing</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-bg-light:hover { background-color: var(--bs-light) !important; cursor: pointer; }
</style>
@endsection
