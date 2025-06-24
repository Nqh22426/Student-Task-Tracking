// Function to open task details modal
function openTaskDetails(taskId) {
    const modal = new bootstrap.Modal(document.getElementById('viewTaskModal'));
    modal.show();
    
    // Set loading state
    document.getElementById('taskDetailsContent').innerHTML = `
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    // Fetch task details
    fetch(`get_task_details.php?task_id=${taskId}`)
        .then(response => response.json())
        .then(data => {
            // Format dates
            const startDate = new Date(data.start_datetime);
            const endDate = new Date(data.end_datetime);
            
            const startFormatted = startDate.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                hour12: true
            });
            
            const endFormatted = endDate.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                hour12: true
            });
            
            // Calculate status
            const now = new Date();
            let statusText = '';
            let statusClass = '';
            
            if (now < startDate) {
                statusText = 'Upcoming';
                statusClass = 'bg-secondary';
            } else if (now >= startDate && now <= endDate) {
                statusText = 'Ongoing';
                statusClass = 'bg-primary';
            } else {
                statusText = 'Completed';
                statusClass = 'bg-completed';
            }
            
            // Build task details HTML
            let taskDetailsHTML = `
                <h4 class="task-title-header mb-4">${data.title}</h4>
                
                <div class="row mb-4">
                    <div class="${data.pdf_file ? 'col-md-6' : 'col-12'}">
                        <h6 class="description-label">Description:</h6>
                        <div class="text-pre-wrap">${data.description ? data.description : 'No description provided.'}</div>
                    </div>
                    
                    ${data.pdf_file ? `
                    <div class="col-md-6">
                        <h6 class="document-label">Document:</h6>
                        <div class="pdf-container">
                            <i class="bi bi-file-pdf pdf-icon"></i>
                            <div class="pdf-title">Task File</div>
                            <div class="pdf-actions">
                                <a href="../${data.pdf_file}" target="_blank" class="btn btn-sm btn-primary me-2">
                                    <i class="bi bi-eye me-1"></i> View
                                </a>
                                <a href="../${data.pdf_file}" download class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download me-1"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="description-label">Starts:</h6>
                        <p>${startFormatted}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="description-label">Due:</h6>
                        <p>${endFormatted}</p>
                    </div>
                </div>
                
                <div class="mb-2">
                    <h6 class="description-label">Status:</h6>
                    <span class="badge ${statusClass} py-2 px-3">${statusText}</span>
                </div>
            `;
            
            // Add submission section
            taskDetailsHTML += `<div class="submission-section mt-4">
                <h5 style="color: #28a745; font-weight: 500;">Your Submission</h5>`;
            
            // Check if there's an existing submission
            if (data.has_submission) {
                taskDetailsHTML += `
                    <div class="submission-container" style="border: none; background-color: transparent; margin-bottom: 0;">
                        <div class="submission-content" style="padding: 0; position: relative; padding-bottom: 45px;">
                            <a href="${data.submission_path}" target="_blank" class="file-btn">
                                <i class="bi bi-file-pdf me-2"></i>${data.submission_filename}
                            </a>
                            ${data.is_locked ? '' : `
                                <a href="unsubmit_task.php?task_id=${data.id}&redirect=class_details.php?id=${data.class_id}"
                                   class="btn btn-outline-danger unsubmit-btn"
                                   onclick="return confirm('Are you sure you want to remove this submission? You can submit again later.')">
                                    <i class="bi bi-x-circle me-1"></i> Unsubmit
                                </a>
                            `}
                        </div>
                    </div>
                `;
            } else if (statusText === 'Upcoming') {
                // For upcoming tasks without submission
                taskDetailsHTML += `
                    <div class="alert alert-info">
                        This task is not yet started. You can submit your work once it begins.
                    </div>
                `;
            } else if (data.is_locked) {
                // For locked tasks without submission, show lock message
                taskDetailsHTML += `
                    <div class="alert alert-warning">
                        <i class="bi bi-lock-fill me-2"></i>
                        This task has been locked by your teacher. You cannot submit at this time.
                    </div>
                `;
            } else {
                // For both Ongoing and Completed tasks without submission, show upload form       
                taskDetailsHTML += `
                    <form id="task-submission-form" action="submit_task.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="task_id" value="${data.id}">
                        <input type="hidden" name="referer" value="class_details.php?id=${data.class_id}">
                        <input type="hidden" name="MAX_FILE_SIZE" value="31457280"> <!-- 30MB limit -->
                        
                        <div class="upload-area p-3 bg-light rounded text-center cursor-pointer mb-3" id="upload-area">
                            <i class="bi bi-cloud-arrow-up fs-3 text-primary mb-2"></i>
                            <h6>Upload your PDF file</h6>
                            <p class="small text-muted">Click to select a file or drag and drop (Max 30MB)</p>
                            <input type="file" name="submission_file" id="submission-file" accept=".pdf" class="d-none">
                        </div>
                        
                        <div class="file-selected d-none" id="file-selected">
                            <i class="bi bi-file-pdf"></i>
                            <span id="selected-filename">No file selected</span>
                            <button type="button" class="btn btn-sm btn-outline-danger float-end" id="remove-file">
                                <i class="bi bi-x"></i> Remove
                            </button>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Submit Task</button>
                        </div>
                    </form>
                `;
            }
            
            taskDetailsHTML += `</div>`; // Close submission section
            
            // Update the task details content
            document.getElementById('taskDetailsContent').innerHTML = taskDetailsHTML;
            
            // Find and hide any unsubmit buttons if task is locked
            if (data.is_locked) {
                setTimeout(function() {
                    document.querySelectorAll('a[href*="unsubmit_task.php"], .unsubmit-btn').forEach(function(btn) {
                        btn.style.display = 'none';
                        btn.style.visibility = 'hidden';
                        btn.style.opacity = '0';
                    });
                    
                    hideUploadForms();
                }, 0);
            }
            
            // Save the task lock state to the modal for future reference
            document.getElementById('viewTaskModal').dataset.isLocked = data.is_locked ? 'true' : 'false';
            
            // Initialize file upload handling if showing the upload form
            if (!data.has_submission && (statusText === 'Ongoing' || statusText === 'Completed') && !data.is_locked) {
                initializeFileUpload();
            }
            
            // Dispatch a custom event to update the unsubmit button visibility
            document.dispatchEvent(new CustomEvent('taskLockChanged', {
                detail: { isLocked: data.is_locked }
            }));
        })
        .catch(error => {
            console.error('Error fetching task details:', error);
            document.getElementById('taskDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    An error occurred while loading task details. Please try again.
                </div>
            `;
        });
}

// Listen for lock/unlock actions from teacher interface
document.addEventListener('DOMContentLoaded', function() {
    const xhrOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function() {
        this.addEventListener('load', function() {
            if (this.responseURL && this.responseURL.includes('toggle_task_lock.php')) {
                try {
                    const response = JSON.parse(this.responseText);
                    if (response.success && typeof response.is_locked !== 'undefined') {
                        // Dispatch custom event to update UI
                        document.dispatchEvent(new CustomEvent('taskLockChanged', {
                            detail: { isLocked: response.is_locked }
                        }));
                        
                        // Also directly update UI based on lock status
                        if (response.is_locked) {
                            setTimeout(function() {
                                forceHideUnsubmitButtons();
                                hideUploadForms();
                            }, 0);
                        }
                    }
                } catch (e) {
                    console.error('Error parsing lock toggle response:', e);
                }
            }
        });
        return xhrOpen.apply(this, arguments);
    };
});

// Initialize file upload functionality
window.initializeFileUpload = function() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('submission-file');
    const fileSelected = document.getElementById('file-selected');
    const selectedFilename = document.getElementById('selected-filename');
    const removeFileBtn = document.getElementById('remove-file');
    
    if (uploadArea && fileInput) {

        // Handle click on upload area
        uploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Handle drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('bg-light-hover');
        });
        
        uploadArea.addEventListener('dragleave', function() {
            uploadArea.classList.remove('bg-light-hover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('bg-light-hover');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelection();
            }
        });
        
        // Handle file selection
        fileInput.addEventListener('change', handleFileSelection);
        
        function handleFileSelection() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                // Validate file type
                if (file.type !== 'application/pdf') {
                    alert('Please select a PDF file.');
                    fileInput.value = '';
                    return;
                }
                
                // Validate file size (30MB max)
                if (file.size > 30 * 1024 * 1024) {
                    alert('File size exceeds 30MB limit.');
                    fileInput.value = '';
                    return;
                }
                
                // Show selected file
                selectedFilename.textContent = file.name;
                uploadArea.style.display = 'none';
                fileSelected.classList.remove('d-none');
            }
        }
        
        // Handle remove file
        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.value = '';
                fileSelected.classList.add('d-none');
                uploadArea.style.display = 'block';
            });
        }
    } else {
        console.warn("Could not find file upload components");
    }
}

// Function to directly hide unsubmit buttons
function forceHideUnsubmitButtons() {
    document.querySelectorAll('a[href*="unsubmit_task.php"], .unsubmit-btn').forEach(button => {
        button.style.display = 'none';
        button.style.visibility = 'hidden';
        button.style.opacity = '0';
    });
}

// Function to actively check and update unsubmit button visibility
function checkAndUpdateUnsubmitVisibility() {
    const modal = document.getElementById('viewTaskModal');
    if (!modal) return;
    
    const isLocked = modal.dataset.isLocked === 'true';
    
    if (isLocked) {
        forceHideUnsubmitButtons();
    }
}

// Set interval to continuously check for unsubmit buttons
setInterval(function() {
    const modal = document.getElementById('viewTaskModal');
    if (modal && modal.classList.contains('show') && modal.dataset.isLocked === 'true') {
        forceHideUnsubmitButtons();
        hideUploadForms();
    }
}, 500);

// Function to hide upload forms and replace them with warning messages
function hideUploadForms() {
    
    // Find any upload forms and submission buttons
    const uploadForms = document.querySelectorAll('#task-submission-form');
    const uploadAreas = document.querySelectorAll('#upload-area');
    const submitButtons = document.querySelectorAll('#task-submission-form button[type="submit"]');
    
    // Hide them
    uploadForms.forEach(form => {
        form.style.display = 'none';
        form.style.visibility = 'hidden';
        form.style.opacity = '0';
    });
    
    uploadAreas.forEach(area => {
        area.style.display = 'none';
        area.style.visibility = 'hidden';
        area.style.opacity = '0';
    });
    
    submitButtons.forEach(btn => {
        btn.style.display = 'none';
        btn.style.visibility = 'hidden';
        btn.style.opacity = '0';
    });
    
    // If no warning message exists yet, add one
    const submissionSection = document.querySelector('.submission-section');
    if (submissionSection && !submissionSection.querySelector('.alert-warning')) {
        const existingAlerts = submissionSection.querySelectorAll('.alert');
        
        // Replace existing alerts or add a new warning
        if (existingAlerts.length > 0) {
            existingAlerts.forEach(alert => {
                alert.outerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-lock-fill me-2"></i>
                        This task has been locked by your teacher. You cannot submit at this time.
                    </div>
                `;
            });
        } else if (!submissionSection.querySelector('#task-submission-form')) {
            // If no form exists, add the warning
            submissionSection.innerHTML += `
                <div class="alert alert-warning">
                    <i class="bi bi-lock-fill me-2"></i>
                    This task has been locked by your teacher. You cannot submit at this time.
                </div>
            `;
        }
    }
} 