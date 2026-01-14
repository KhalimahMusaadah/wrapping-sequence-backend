<h4 class="mb-4">Manual Testing</h4>

<div class="row">
    <div class="col-md-6">
        <div class="card p-3">
            <h6>Test READY</h6>

            <form id="form-test-ready">
                <input type="text" id="ready-mac"
                       class="form-control mb-3"
                       value="IOT_dummymacaddresswrapping" required>

                <div class="form-check mb-3">
                    <input type="checkbox" id="ready-test-mode"
                           class="form-check-input" checked>
                    <label class="form-check-label">Test Mode</label>
                </div>

                <button class="btn btn-primary w-100">
                    Send READY
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card p-3">
            <h6>Test DONE</h6>

            <form id="form-test-done">
                <input type="text" id="done-mac"
                       class="form-control mb-3"
                       value="IOT_dummymacaddresswrapping" required>

                <button class="btn btn-success w-100">
                    Send DONE
                </button>
            </form>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        API Response
    </div>
    <div class="card-body">
        <pre id="response-display">Waiting...</pre>
        <button class="btn btn-sm btn-outline-secondary mt-2"
                id="clear-response">
            Clear
        </button>
    </div>
</div>
