<div class="d-flex justify-content-between mb-4">
    <h4>Monitoring</h4>
    <div>
        <span class="badge bg-success" id="live-indicator">LIVE</span>
        <small class="text-muted ms-2" id="last-update">--:--</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center p-3">
            <small class="text-muted text-uppercase">Today's Total</small>
            <h2 id="total-wraps">0</h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center p-3">
            <small class="text-muted text-uppercase">Current Sequence</small>
            <h2 id="current-sequence">0</h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center p-3">
            <small class="text-muted text-uppercase">Last Task</small>
            <h2 id="last-task">-</h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center p-3">
            <small class="text-muted text-uppercase">Last Activity</small>
            <h5 id="last-time">-</h5>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <strong>Recent History</strong>
        <button class="btn btn-sm btn-outline-primary" id="refresh-history">
            Refresh
        </button>
    </div>

    <div class="card-body table-responsive">
        <table class="table table-hover text-center">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Counter</th>
                    <th>Sequence</th>
                    <th>Task</th>
                    <th>MAC</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="history-tbody">
                <tr>
                    <td colspan="6">Loading...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
