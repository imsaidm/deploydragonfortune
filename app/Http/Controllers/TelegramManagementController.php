<?php

namespace App\Http\Controllers;

use App\Models\QcMethod;
use App\Models\QcTelegramChannel;
use Illuminate\Http\Request;

class TelegramManagementController extends Controller
{
    /**
     * Display management dashboard.
     */
    public function index()
    {
        $channels = QcTelegramChannel::with('methods')->get();
        $methods = QcMethod::orderBy('nama_metode')->get();
        
        return view('admin.telegram.index', compact('channels', 'methods'));
    }

    /**
     * Store a new channel.
     */
    public function storeChannel(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'chat_id' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        QcTelegramChannel::create($validated);

        return back()->with('success', 'Telegram channel added successfully.');
    }

    /**
     * Update channel status or info.
     */
    public function updateChannel(Request $request, QcTelegramChannel $channel)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'chat_id' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $channel->update($validated);

        return back()->with('success', 'Channel updated.');
    }

    /**
     * Delete a channel.
     */
    public function deleteChannel(QcTelegramChannel $channel)
    {
        $channel->methods()->detach();
        $channel->delete();

        return back()->with('success', 'Channel deleted.');
    }

    /**
     * Link methods to a channel.
     */
    public function syncMethods(Request $request, QcTelegramChannel $channel)
    {
        $channel->methods()->sync($request->method_ids ?? []);

        return back()->with('success', 'Routing updated successfully.');
    }
}
