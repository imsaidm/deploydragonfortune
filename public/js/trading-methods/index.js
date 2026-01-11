// Initialize DataTable
let table;

// Configuration for badges and logic
const getCagrBadge = (val) => val > 50 ? 'bg-success' : (val > 30 ? 'bg-warning' : 'bg-danger');
const getWinrateBadge = (val) => val > 60 ? 'bg-success' : (val > 50 ? 'bg-warning' : 'bg-danger');
const getDrawdownBadge = (val) => val < 10 ? 'bg-success' : (val < 20 ? 'bg-warning' : 'bg-danger');

const waitForJQuery = (callback) => {
    if (window.jQuery && window.Swal && window.jQuery.fn.DataTable) {
        callback(window.jQuery);
    } else {
        setTimeout(() => waitForJQuery(callback), 100);
    }
};

waitForJQuery(($) => {
    $(document).ready(function() {
        // Load Master Exchange options
        loadMasterExchangeOptions();
        
        try {
            table = $('#methodsTable').DataTable({
                processing: true,
                serverSide: false, // Client-side filtering/sorting for now since dataset is small
                ajax: {
                    url: '/trading-methods',
                    dataSrc: 'data'
                },
                order: [[0, 'asc']], // Sort by Name default, or created_at if returned
                pageLength: 25,
                responsive: true,
                columns: [
                    { 
                        data: 'nama_metode',
                        render: function(data, type, row) {
                            return `
                                <strong>${data}</strong><br>
                                <small class="text-muted">${row.exchange}</small>
                            `;
                        }
                    },
                    { 
                        data: 'market_type',
                        render: function(data) {
                            const color = data === 'FUTURES' ? 'warning' : 'success';
                            return `<span class="badge bg-${color}">${data}</span>`;
                        }
                    },
                    { data: 'pair' },
                    { data: 'tf' },
                    { 
                        data: 'cagr',
                        render: function(data) {
                            if (!data) return '<span class="text-muted">-</span>';
                            const num = parseFloat(data).toFixed(2);
                            return `<span class="badge ${getCagrBadge(data)}">${num}%</span>`;
                        }
                    },
                    { 
                        data: 'winrate',
                        render: function(data) {
                            if (!data) return '<span class="text-muted">-</span>';
                            const num = parseFloat(data).toFixed(2);
                            return `<span class="badge ${getWinrateBadge(data)}">${num}%</span>`;
                        }
                    },
                    { 
                        data: 'drawdown',
                        render: function(data) {
                            if (!data) return '<span class="text-muted">-</span>';
                            const num = parseFloat(data).toFixed(2);
                            return `<span class="badge ${getDrawdownBadge(data)}">${num}%</span>`;
                        }
                    },
                    { 
                        data: 'is_active',
                        render: function(data, type, row) {
                            const checked = data ? 'checked' : '';
                            return `
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           data-id="${row.id}" 
                                           onchange="toggleActive(this)"
                                           ${checked}>
                                </div>
                            `;
                        }
                    },
                    { 
                        data: 'auto_trade_enabled',
                        render: function(data, type, row) {
                            const checked = data ? 'checked' : '';
                            return `
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           data-id="${row.id}" 
                                           onchange="toggleAutoTrade(this)"
                                           ${checked}>
                                </div>
                            `;
                        }
                    },
                    { 
                        data: 'id',
                        orderable: false,
                        render: function(data) {
                            return `
                                <button class="btn btn-sm btn-outline-primary" onclick="viewMethod(${data})">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="editMethod(${data})">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="exportMethod(${data})">
                                    <i class="bi bi-download"></i> Export
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteMethod(${data})">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            `;
                        }
                    }
                ]
            });
        } catch (error) {
            console.error('DataTable initialization error:', error);
        }
    });
});

// Helper for Notifications using SweetAlert2
function showNotification(message, type = 'success') {
    if (typeof window.Swal === 'undefined') {
        alert(message);
        return;
    }
    
    const icon = type === 'success' ? 'success' : 'error';
    const title = type === 'success' ? 'Success!' : 'Error!';
    
    // Toast style for unobtrusive notifications
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    Toast.fire({
        icon: icon,
        title: message
    });
}

// Global functions exposed for onclick handlers
window.openCreateModal = function() {
    $('#modalTitle').text('Create Trading Method');
    $('#methodId').val('');
    $('#methodForm')[0].reset();
    
    // Reset Master Exchange dropdown
    loadMasterExchangeOptions();
    
    const firstTab = document.querySelector('.nav-tabs a:first-child');
    if (firstTab && window.bootstrap) {
        new bootstrap.Tab(firstTab).show();
    }
};

// Load Master Exchange options
function loadMasterExchangeOptions() {
    $.ajax({
        url: '/master-exchanges',
        method: 'GET',
        success: function(response) {
            if (response.data) {
                const select = $('#master_exchange_id');
                select.html('<option value="">-- Use Custom API Keys Below --</option>');
                
                response.data.forEach(exchange => {
                    if (exchange.is_active) {
                        select.append(`<option value="${exchange.id}">${exchange.name} (${exchange.exchange_type})</option>`);
                    }
                });
            }
        },
        error: function() {
            console.warn('Failed to load master exchanges');
        }
    });
}

window.viewMethod = function(id) {
    $.ajax({
        url: `/trading-methods/${id}`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                populateForm(response.data, true); // true = readonly
                $('#modalTitle').text('View Trading Method');
                $('.modal-footer').hide();
                new bootstrap.Modal(document.getElementById('methodModal')).show();
            }
        },
        error: handleError
    });
};

window.editMethod = function(id) {
    $.ajax({
        url: `/trading-methods/${id}`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                populateForm(response.data, false);
                $('#modalTitle').text('Edit Trading Method');
                $('.modal-footer').show();
                new bootstrap.Modal(document.getElementById('methodModal')).show();
            }
        },
        error: handleError
    });
};

window.saveMethod = function() {
    const id = $('#methodId').val();
    const isEdit = id !== '';
    
    const formData = {
        nama_metode: $('#nama_metode').val(),
        market_type: $('#market_type').val(),
        pair: $('#pair').val(),
        tf: $('#tf').val(),
        exchange: $('#exchange').val(),
        master_exchange_id: $('#master_exchange_id').val() || null,
        cagr: $('#cagr').val() || null,
        drawdown: $('#drawdown').val() || null,
        winrate: $('#winrate').val() || null,
        lossrate: $('#lossrate').val() || null,
        prob_sr: $('#prob_sr').val() || null,
        sharpen_ratio: $('#sharpen_ratio').val() || null,
        sortino_ratio: $('#sortino_ratio').val() || null,
        information_ratio: $('#information_ratio').val() || null,
        turnover: $('#turnover').val() || null,
        total_orders: $('#total_orders').val() || null,
        kpi_extra: $('#kpi_extra').val() || null,
        qc_url: $('#qc_url').val(),
        qc_project_id: $('#qc_project_id').val() || null,
        webhook_token: $('#webhook_token').val() || null,
        risk_settings: $('#risk_settings').val() || null,
    };
    
    const url = isEdit ? `/trading-methods/${id}` : '/trading-methods';
    const method = isEdit ? 'PUT' : 'POST';
    
    $.ajax({
        url: url,
        method: method,
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: response.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('methodModal'));
                if (modal) modal.hide();
                table.ajax.reload(null, false); // Reload table without refresh
            }
        },
        error: handleError
    });
};

window.deleteMethod = function(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/trading-methods/${id}`,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Deleted!', response.message, 'success');
                        table.ajax.reload(null, false);
                    }
                },
                error: handleError
            });
        }
    });
};

window.toggleActive = function(checkbox) {
    const id = $(checkbox).data('id');
    $.ajax({
        url: `/trading-methods/${id}/toggle-active`,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            showNotification(response.message, 'success');
        },
        error: function(xhr) {
            $(checkbox).prop('checked', !$(checkbox).prop('checked'));
            showNotification('Error updating status', 'error');
        }
    });
};

window.toggleAutoTrade = function(checkbox) {
    const id = $(checkbox).data('id');
    $.ajax({
        url: `/trading-methods/${id}/toggle-auto-trade`,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            showNotification(response.message, 'success');
        },
        error: function(xhr) {
            $(checkbox).prop('checked', !$(checkbox).prop('checked'));
            showNotification('Error updating status', 'error');
        }
    });
};

window.exportMethod = function(id) {
    window.location.href = `/trading-methods/export/json?ids=${id}`;
};

window.importMethods = function() {
    const fileInput = document.getElementById('importFile');
    const file = fileInput.files[0];
    
    if (!file) {
        Swal.fire('Error', 'Please select a file', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    $.ajax({
        url: '/trading-methods/import/json',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Import Successful',
                    text: response.message,
                });
                
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('importModal'));
                if (modal) modal.hide();
                table.ajax.reload(null, false);
            }
        },
        error: handleError
    });
};

window.generateToken = function() {
    const token = Array.from(crypto.getRandomValues(new Uint8Array(32)))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    $('#webhook_token').val(token);
};

// Helper to populate form
function populateForm(method, disabled) {
    $('#methodId').val(method.id);
    const fields = [
        'nama_metode', 'market_type', 'pair', 'tf', 'exchange',
        'cagr', 'drawdown', 'winrate', 'lossrate', 'prob_sr',
        'sharpen_ratio', 'sortino_ratio', 'information_ratio',
        'turnover', 'total_orders', 'qc_url', 'qc_project_id', 'webhook_token'
    ];
    
    fields.forEach(f => {
        $(`#${f}`).val(method[f]).prop('disabled', disabled);
    });
    
    // Master Exchange
    $('#master_exchange_id').val(method.master_exchange_id || '').prop('disabled', disabled);
    
    // Handle JSON and Secrets
    $('#kpi_extra').val(method.kpi_extra ? JSON.stringify(method.kpi_extra, null, 2) : '').prop('disabled', disabled);
    $('#risk_settings').val(method.risk_settings ? JSON.stringify(method.risk_settings, null, 2) : '').prop('disabled', disabled);
}

function handleError(xhr) {
    let msg = 'Unknown error occurred';
    if (xhr.responseJSON && xhr.responseJSON.message) {
        msg = xhr.responseJSON.message;
        if (xhr.responseJSON.errors) {
            msg += '\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
        }
    }
    Swal.fire('Error', msg, 'error');
}
