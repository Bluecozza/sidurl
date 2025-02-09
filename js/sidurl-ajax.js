document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('sidurl-form');
    if (!form) return;

    const result = document.getElementById('sidurl-result');
    const button = form.querySelector('button[type="submit"]');
    const originalButtonText = button.innerHTML;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Reset state
        result.innerHTML = '';
        button.disabled = true;
        button.innerHTML = sidurl_data.i18n.processing;

        const formData = new FormData(form);
        formData.append('action', 'sidurl_generate');
        formData.append('nonce', sidurl_data.nonce);

        fetch(sidurl_data.ajax_url, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                result.innerHTML = `
                    <div class="success">
                        <p>${sidurl_data.i18n.success}</p>
                        <div class="shorturl-container">
                            <input type="text" 
                                   value="${data.data.short_url}" 
                                   id="shorturl-output" 
                                   readonly>
                            <button class="copy-button" 
                                    data-clipboard-target="#shorturl-output">
                                Copy
                            </button>
                        </div>
                    </div>
                `;

                // Initialize clipboard
                new ClipboardJS('.copy-button')
                    .on('success', function(e) {
                        e.trigger.textContent = sidurl_data.i18n.copied;
                        setTimeout(() => {
                            e.trigger.textContent = `Copy`;
                        }, 2000);
                    });
            } else {
                result.innerHTML = `<div class="error">${data.data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            result.innerHTML = `<div class="error">${sidurl_data.i18n.error}</div>`;
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalButtonText;
        });
    });
});
