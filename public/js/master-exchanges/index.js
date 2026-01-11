// Initialize DataTable
let table;

// Badge helpers
const getExchangeBadge = (type) => {
    const badges = {
        'BINANCE': 'badge-binance',
        'BYBIT': 'badge-bybit',
        'OKX': 'badge-okx'
    };
    return badges[type] || 'bg-secondary';
};

const waitForJQuery = (callback) => {
    if (window.jQuery && window.Swal && window.jQuery.fn.DataTable) {
        callback(window.jQuery);
    } else {
        setTimeout(() => waitForJQuery(callback), 100);
    }
};

waitForJQuery(($) => {
    $(document).ready(function() {
        try {
            table = $('#exchangesTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '/master-exchanges',
                    dataSrc: 'data'
                },
                order: [[0, 'asc']],
                pageLength: 25,
                responsive: true,
                columns: [
                    { 
                        data: 'name',
                        render: function(data, type, row) {
                            return `<strong>${data}</strong>`;
                        }
                    },
                    { 
                        data: 'exchange_type',
                        render: function(data) {
                            return `<span class="badge bg-primary ${getExchangeBadge(data)}">${data}</span>`;
                        }
                    },
                    { 
                        data: 'market_type',
                        render: function(data) {
                            const color = data === 'SPOT' ? 'success' : 'warning';
                            return `<span class="badge bg-${color}">${data}</span>`;
                        }
                    },
                    { 
                        data: 'testnet',
                        render: function(data) {
                            return data 
                                ? '<span class="badge bg-warning">Testnet</span>' 
                                : '<span class="badge bg-success">Live</span>';
                        }
                    },
                    { 
                        data: 'trading_methods_count',
                        render: function(data) {
                            return data || 0;
                        }
                    },
                    { 
                        data: 'last_validated_at',
                        render: function(data) {
                            if (!data) return '<span class="text-muted">Never</span>';
                            const date = new Date(data);
                            return `<small>${date.toLocaleString()}</small>`;
                        }
                    },
                    { 
                        data: 'is_active',
                        render: function(data, type, row) {
                            const checked = data ? 'checked' : '';
                            const statusClass = data ? 'status-active' : 'status-inactive';
                            return `
                                <div class="form-check form-switch">
                                    <span class="status-indicator ${statusClass}"></span>
                                    <input class="form-check-input" type="checkbox" 
                                           data-id="${row.id}" 
                                           onchange="toggleActive(this)"
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
                                <div class="btn-group btn-group-sm" style="gap: 5px;" role="group">
                                    <button class="btn btn-outline-primary" onclick="viewExchange(${data})" title="View">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="editExchange(${data})" title="Edit">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-outline-info" onclick="testConnection(${data})" title="Test Connection">
                                        <i class="bi bi-wifi"></i> Test
                                    </button>
                                    <button class="btn btn-outline-success" onclick="viewBalance(${data})" title="View Balance">
                                        <i class="bi bi-wallet2"></i> Balance
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteExchange(${data})" title="Delete">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
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

// Helper for Notifications
function showNotification(message, type = 'success') {
    if (typeof window.Swal === 'undefined') {
        alert(message);
        return;
    }
    
    const icon = type === 'success' ? 'success' : 'error';
    
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

// Global functions
window.openCreateModal = function() {
    $('#modalTitle').text('Create Exchange Account');
    $('#exchangeId').val('');
    $('#exchangeForm')[0].reset();
    
    // Enable all form fields (in case they were disabled from view mode)
    $('#name').prop('disabled', false);
    $('#exchange_type').prop('disabled', false);
    $('#market_type').prop('disabled', false);
    $('#api_key').prop('disabled', false).prop('required', true);
    $('#secret_key').prop('disabled', false).prop('required', true);
    $('#testnet').prop('disabled', false);
    $('#description').prop('disabled', false);
    
    // Show modal footer (Save button)
    $('.modal-footer').show();
};

window.viewExchange = function(id) {
    $.ajax({
        url: `/master-exchanges/${id}`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                populateForm(response.data, true);
                $('#modalTitle').text('View Exchange Account');
                $('.modal-footer').hide();
                new bootstrap.Modal(document.getElementById('exchangeModal')).show();
            }
        },
        error: handleError
    });
};

window.editExchange = function(id) {
    $.ajax({
        url: `/master-exchanges/${id}`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                populateForm(response.data, false);
                $('#modalTitle').text('Edit Exchange Account');
                $('.modal-footer').show();
                $('#api_key').prop('required', false);
                $('#secret_key').prop('required', false);
                new bootstrap.Modal(document.getElementById('exchangeModal')).show();
            }
        },
        error: handleError
    });
};

window.saveExchange = function() {
    const id = $('#exchangeId').val();
    const isEdit = id !== '';
    
    const formData = {
        name: $('#name').val(),
        exchange_type: $('#exchange_type').val(),
        market_type: $('#market_type').val(),
        api_key: $('#api_key').val(),
        secret_key: $('#secret_key').val(),
        testnet: $('#testnet').is(':checked') ? 1 : 0,
        description: $('#description').val() || null,
    };
    
    const url = isEdit ? `/master-exchanges/${id}` : '/master-exchanges';
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
                
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('exchangeModal'));
                if (modal) modal.hide();
                table.ajax.reload(null, false);
            }
        },
        error: handleError
    });
};

window.deleteExchange = function(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will affect all trading methods using this exchange!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/master-exchanges/${id}`,
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
        url: `/master-exchanges/${id}/toggle-active`,
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

window.testConnection = function(id) {
    Swal.fire({
        title: 'Testing Connection...',
        text: 'Please wait while we validate your API credentials',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: `/master-exchanges/${id}/test-connection`,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                Swal.fire({
                    icon: 'success',
                    title: 'Connection Successful!',
                    html: `
                        <div class="text-start">
                            <p><strong>API Status:</strong></p>
                            <ul>
                                <li>Can Trade: ${data.canTrade ? '✅' : '❌'}</li>
                                <li>Can Deposit: ${data.canDeposit ? '✅' : '❌'}</li>
                                <li>Can Withdraw: ${data.canWithdraw ? '✅' : '❌'}</li>
                                <li>Wallet Balance: $${parseFloat(data.totalWalletBalance).toFixed(2)}</li>
                            </ul>
                        </div>
                    `
                });
                table.ajax.reload(null, false);
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            Swal.fire({
                icon: 'error',
                title: 'Connection Failed',
                text: response?.message || 'Invalid API credentials or network error'
            });
        }
    });
};

window.viewBalance = function(id) {
    const modal = new bootstrap.Modal(document.getElementById('balanceModal'));
    modal.show();
    
    $('#balanceContent').html(`
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    
    $.ajax({
        url: `/master-exchanges/${id}/balance`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.data.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-sm">';
                html += '<thead><tr><th>Asset</th><th>Balance</th><th>Available</th></tr></thead><tbody>';
                
                response.data.forEach(balance => {
                    html += `
                        <tr>
                            <td><strong>${balance.asset}</strong></td>
                            <td>${parseFloat(balance.balance).toFixed(8)}</td>
                            <td>${parseFloat(balance.availableBalance || balance.balance).toFixed(8)}</td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                $('#balanceContent').html(html);
            } else {
                $('#balanceContent').html('<p class="text-muted text-center">No balance data available</p>');
            }
        },
        error: function(xhr) {
            $('#balanceContent').html('<p class="text-danger text-center">Failed to fetch balance</p>');
        }
    });
};

// Helper to populate form
function populateForm(exchange, disabled) {
    $('#exchangeId').val(exchange.id);
    $('#name').val(exchange.name).prop('disabled', disabled);
    $('#exchange_type').val(exchange.exchange_type).prop('disabled', disabled);
    $('#market_type').val(exchange.market_type || 'FUTURES').prop('disabled', disabled);
    $('#testnet').prop('checked', exchange.testnet).prop('disabled', disabled);
    $('#description').val(exchange.description).prop('disabled', disabled);
    
    // Masked credentials
    $('#api_key').val(exchange.api_key || '').prop('disabled', disabled);
    $('#secret_key').val(exchange.secret_key || '').prop('disabled', disabled);
    
    if (!disabled) {
        $('#api_key').attr('placeholder', 'Leave empty to keep current');
        $('#secret_key').attr('placeholder', 'Leave empty to keep current');
    }
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
