const Dashboard = {

    baseUrl: window.location.origin,
    refreshInterval: 5000,

    initMonitoring() {
        this.loadSummary();
        this.loadHistory();

        setInterval(() => {
            this.loadSummary();
            this.loadHistory();
        }, this.refreshInterval);

        $('#refresh-history').click(() => {
            this.loadHistory();
        });
    },

    initTesting() {
        $('#form-test-ready').submit(e => {
            e.preventDefault();
            this.testReady();
        });

        $('#form-test-done').submit(e => {
            e.preventDefault();
            this.testDone();
        });

        $('#clear-response').click(() => {
            $('#response-display').text('Waiting...');
        });
    },

    loadSummary() {
        $.get(this.baseUrl + '/dashboard/getSummary', res => {

            $('#total-wraps').text(res.data.total_wraps);
            $('#current-sequence').text(res.data.current_sequence);
            $('#last-task').text(res.data.last_task || '-');

            if (res.data.last_time) {
                let t = new Date(res.data.last_time);
                $('#last-time').text(t.toLocaleTimeString());
            }

            $('#last-update').text(
                'Updated: ' + new Date().toLocaleTimeString()
            );
        });
    },

    loadHistory() {
        $.get(this.baseUrl + '/dashboard/getRecentHistory', {limit:10}, res => {
            let html = '';

            res.data.forEach(r => {
                html += `
                <tr>
                    <td>${r.time}</td>
                    <td>${r.counter}</td>
                    <td>${r.sequence}</td>
                    <td>${r.taskId}</td>
                    <td>${r.mac_address}</td>
                    <td>
                        <span class="badge bg-success">
                            ${r.status}
                        </span>
                    </td>
                </tr>`;
            });

            $('#history-tbody').html(html);
        });
    },

    testReady() {
        let mac = $('#ready-mac').val();
        let test = $('#ready-test-mode').is(':checked');

        this.send(
            '/dashboard/testReady',
            {mac_address:mac,test_mode:test}
        );
    },

    testDone() {
        let mac = $('#done-mac').val();

        this.send(
            '/dashboard/testDone',
            {mac_address:mac}
        );
    },

    send(url,data) {
        $('#response-display').text('Sending...');

        $.ajax({
            url:this.baseUrl+url,
            method:'POST',
            contentType:'application/json',
            data:JSON.stringify(data),
            success:r=>{
                $('#response-display')
                    .text(JSON.stringify(r,null,2));
            }
        });
    }
};
