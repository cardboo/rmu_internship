function updateStatus(requestId, newStatus) {
    // Confirm action
    if(!confirm('Are you sure you want to ' + newStatus + ' this request?')) return;

    const formData = new FormData();
    formData.append('id', requestId);
    formData.append('status', newStatus);

    fetch('process_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Update UI without refresh
            const statusCell = document.getElementById(`status-${requestId}`);
            const actionCell = document.getElementById(`actions-${requestId}`);

            // Safety check to ensure elements exist
            if (statusCell && actionCell) {
                // Update Status Badge
                statusCell.innerHTML = `<span class="status-badge ${newStatus}">${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}</span>`;

                // Update Action Buttons dynamically
                if (newStatus === 'approved') {
                    actionCell.innerHTML = `
                        <a href="generate_letter.php?id=${requestId}" target="_blank" style="text-decoration:none;">
                            <button style="width: auto; background: var(--dark); padding: 5px 10px; font-size: 0.8rem; cursor: pointer;">Download Letter (PDF)</button>
                        </a>`;
                } else {
                    actionCell.innerHTML = '<span style="color: grey; font-size: 0.8rem;">Rejected</span>';
                }
            }
        } else {
            alert('Server error: Could not update status.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An unexpected error occurred.');
    });
}

function rejectWithComment(requestId) {
    const reason = prompt("Please enter the reason for rejection (e.g., 'Company name misspelled' or 'Date range too short'):");
    
    // If the user clicked cancel or left it empty, don't proceed
    if (reason === null) return; 
    
    if (reason.trim() === "") {
        alert("A reason is required to reject a request.");
        return;
    }

    // Call your existing updateStatus function, but pass the reason as a third argument
    updateStatus(requestId, 'rejected', reason);
}

// Updated updateStatus function to handle the comment
function updateStatus(id, status, comment = "") {
    fetch('update_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&status=${status}&comment=${encodeURIComponent(comment)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Refresh to show changes and the new status
        } else {
            alert("Error: " + data.message);
        }
    });
}