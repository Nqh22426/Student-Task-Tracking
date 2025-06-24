// Script to fix task status badge colors and position submission section
document.addEventListener('DOMContentLoaded', function() {
    console.log("Status and Submission Fix Loaded");
    
    // FIRST: Remove any styles that hide submission headers
    const styles = document.querySelectorAll('style');
    for(let style of styles) {
        if(style.innerHTML.includes('.submission-header { display: none')) {
            console.log("Found and removed style hiding submission headers");
            style.remove();
        }
    }
    
    // Add styles directly for status badge colors and submission styling
    const styleElement = document.createElement('style');
    styleElement.textContent = `
        /* Completed status: green to red */
        .badge.bg-success, 
        .badge.bg-completed,
        span.badge.bg-success {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        /* Ongoing status: blue to yellow */
        .badge.bg-primary,
        .badge.bg-ongoing,
        span.badge.bg-primary {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
            color: #212529 !important;
        }
        
        /* Upcoming status: gray to green */
        .badge.bg-secondary,
        span.badge.bg-secondary {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
            color: white !important;
        }
        
        /* Ensure submission section is visible for ALL task statuses */
        #viewTaskModal .badge.bg-secondary ~ .submission-section,
        #viewTaskModal span.badge.bg-secondary ~ .submission-section,
        #viewTaskModal .badge.bg-completed ~ .submission-section,
        #viewTaskModal .badge.bg-success ~ .submission-section,
        #viewTaskModal span.badge.bg-completed ~ .submission-section,
        #viewTaskModal span.badge.bg-success ~ .submission-section {
            display: block !important;
        }
        
        /* Style the "Your Submission" heading to be green */
        .submission-section h5 {
            color: #28a745 !important;
            font-weight: 500 !important;
        }
        
        /* Styling for the submission container */
        .custom-submission-container {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            overflow: hidden;
            margin-top: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        
        /* Using a different class to avoid the global styling that hides submission-header */
        .custom-submission-header, .submission-header {
            background-color: white !important;
            color: #28a745 !important;
            padding: 8px 12px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            display: block !important;
            border-bottom: 1px solid #e0e0e0 !important;
        }
        
        .submission-content {
            padding: 12px;
            position: relative;
            padding-bottom: 45px;
            background-color: #f0f7ff;
        }
        
        .file-btn {
            display: inline-block;
            padding: 8px 12px;
            background-color: #28a745;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 5px;
        }
        
        .file-btn:hover {
            background-color: #218838;
            color: white;
            text-decoration: none;
        }
        
        .unsubmit-btn {
            display: inline-block;
            width: auto;
            color: #dc3545;
            border-color: #dc3545;
            background-color: white;
            padding: 4px 8px;
            font-size: 0.875rem;
            position: absolute;
            right: 10px;
            bottom: 10px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #dc3545;
        }
        
        .unsubmit-btn:hover {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        /* Hide unsubmit button for locked tasks globally */
        .task-locked .unsubmit-btn,
        body[data-task-locked="true"] .unsubmit-btn {
            display: none !important;
        }
    `;
    document.head.appendChild(styleElement);
    
    // Function to reorganize the submission section
    function reorganizeSubmission() {
        // Get key elements
        const taskContent = document.getElementById('taskDetailsContent');
        if (!taskContent) return false;
        
        const submissionSection = taskContent.querySelector('.submission-section');
        if (!submissionSection) return false;
        
        const fileLink = submissionSection.querySelector('a[href*=".pdf"]');
        if (!fileLink) return false;
        
        const statusSection = taskContent.querySelector('.mb-2:has(.badge)') || 
                             taskContent.querySelector('.mb-2');
        if (!statusSection) return false;
        
        const datesRow = taskContent.querySelector('.row.mb-3');
        if (!datesRow) return false;
        
        // Check if we already reorganized
        if (taskContent.querySelector('.submission-moved')) return true;
        
        // Hide original sections
        statusSection.style.display = 'none';
        submissionSection.style.display = 'none';
        
        // Create new row
        const newRow = document.createElement('div');
        newRow.className = 'row mb-3 mt-3 submission-moved';
        
        // Get file info
        const fileName = fileLink.textContent.trim();
        const unsubmitBtn = submissionSection.querySelector('a[href*="unsubmit"]');
        const unsubmitUrl = unsubmitBtn ? unsubmitBtn.getAttribute('href') : '#';
        
        // Create HTML with custom-submission-header instead of submission-header to avoid the hiding style
        newRow.innerHTML = `
            <div class="col-md-6">
                ${statusSection.outerHTML}
            </div>
            <div class="col-md-6">
                <div class="custom-submission-container">
                    <div class="custom-submission-header">Your Submission</div>
                    <div class="submission-content">
                        <a href="${fileLink.getAttribute('href')}" target="_blank" class="file-btn">
                            <i class="bi bi-file-pdf me-2"></i>${fileName}
                        </a>
                        <a href="${unsubmitUrl}" 
                           class="unsubmit-btn"
                           onclick="return confirm('Are you sure you want to remove this submission?')">
                            <i class="bi bi-x-circle me-1"></i> Unsubmit
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        // Show the status section within the new row
        const newStatusSection = newRow.querySelector('.mb-2');
        if (newStatusSection) {
            newStatusSection.style.display = 'block';
        }
        
        // Insert after dates row
        datesRow.after(newRow);
        return true;
    }
    
    // Function to fix Completed task submission section - replace message with upload form
    function fixCompletedTaskSubmission() {
        const taskContent = document.getElementById('taskDetailsContent');
        if (!taskContent) return;
        
        // Check if this is a Completed task
        const statusBadge = taskContent.querySelector('.badge.bg-completed') || 
                          taskContent.querySelector('.badge.bg-success');
        if (!statusBadge) return;
        
        console.log("Found Completed task, checking submission section");
        
        // Check if there's a submission alert message
        const submissionSection = taskContent.querySelector('.submission-section');
        if (!submissionSection) return;
        
        const alertMessage = submissionSection.querySelector('.alert-info');
        if (alertMessage && alertMessage.textContent.includes('completed and you did not submit')) {
            console.log("Found alert message for Completed task, checking lock status");
            
            // Get task lock status from the modal
            const modal = document.getElementById('viewTaskModal');
            const isLocked = modal && modal.dataset.isLocked === 'true';
            
            // If task is locked, show locked message instead of upload form
            if (isLocked) {
                console.log("Task is locked, showing lock message instead of upload form");
                alertMessage.outerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-lock-fill me-2"></i>
                        This task has been locked by your teacher. You cannot submit at this time.
                    </div>
                `;
                return;
            }
            
            console.log("Task is not locked, replacing alert with upload form");
            
            // Get task ID from the page
            let taskId = '';
            const taskForm = document.querySelector('form[action="submit_task.php"]');
            if (taskForm) {
                const taskIdInput = taskForm.querySelector('input[name="task_id"]');
                if (taskIdInput) {
                    taskId = taskIdInput.value;
                }
            }
            
            if (!taskId) {
                // Try to extract from URL in the page
                const unsubmitLink = document.querySelector('a[href*="unsubmit_task.php"]');
                if (unsubmitLink) {
                    const href = unsubmitLink.getAttribute('href');
                    const match = href.match(/task_id=(\d+)/);
                    if (match && match[1]) {
                        taskId = match[1];
                    }
                }
            }
            
            if (!taskId) {
                // Last resort - try to find any numeric ID that might be the task ID
                const content = taskContent.innerHTML;
                const match = content.match(/task[_\-]?id["']?\s*[:=]\s*["']?(\d+)/i);
                if (match && match[1]) {
                    taskId = match[1];
                }
            }
            
            // Get class ID
            let classId = '';
            const urlParams = new URLSearchParams(window.location.search);
            classId = urlParams.get('id') || '';
            
            // Replace the alert with an upload form
            alertMessage.outerHTML = `
                <form id="task-submission-form" action="submit_task.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="task_id" value="${taskId}">
                    <input type="hidden" name="referer" value="class_details.php?id=${classId}">
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
            
            // Initialize file upload handling - use the function from task_ajax.js
            if (typeof window.initializeFileUpload === 'function') {
                window.initializeFileUpload();
            }
        }
    }
    
    // Function to update the unsubmit button visibility based on lock status
    function updateUnsubmitButtonVisibility(isLocked) {
        console.log("Updating unsubmit button visibility, isLocked:", isLocked);
        
        // Update body data attribute for global CSS targeting
        document.body.setAttribute('data-task-locked', isLocked ? 'true' : 'false');
        
        // Add/remove class from modal
        const modal = document.getElementById('viewTaskModal');
        if (modal) {
            if (isLocked) {
                modal.classList.add('task-locked');
            } else {
                modal.classList.remove('task-locked');
            }
        }
        
        // Only update the visibility of existing unsubmit buttons (direct styling)
        const unsubmitButtons = document.querySelectorAll('.unsubmit-btn');
        unsubmitButtons.forEach(btn => {
            if (isLocked) {
                btn.style.display = 'none !important';
                btn.setAttribute('disabled', 'disabled');
                console.log("Hiding unsubmit button for locked task");
            } else {
                btn.style.display = 'inline-block';
                btn.removeAttribute('disabled');
                console.log("Showing unsubmit button for unlocked task");
            }
        });
        
        // Force direct hide with inline style for buttons that don't get caught otherwise
        setTimeout(function() {
            if (isLocked) {
                document.querySelectorAll('a[href*="unsubmit_task.php"]').forEach(link => {
                    link.style.display = 'none';
                });
            }
        }, 100);
    }

    // Listen for Lock/Unlock events via custom event
    document.addEventListener('taskLockChanged', function(e) {
        if (e.detail && typeof e.detail.isLocked !== 'undefined') {
            updateUnsubmitButtonVisibility(e.detail.isLocked);
        }
    });
    
    // Listen for modal shown event - this handles the first open after page load
    const viewTaskModal = document.getElementById('viewTaskModal');
    if (viewTaskModal) {
        viewTaskModal.addEventListener('shown.bs.modal', function() {
            // Try immediately and then with a delay to ensure content is loaded
            if (!reorganizeSubmission()) {
                setTimeout(reorganizeSubmission, 300);
            }
            
            // Check if task is locked from data attribute and update UI
            const isLocked = viewTaskModal.dataset.isLocked === 'true';
            updateUnsubmitButtonVisibility(isLocked);
            
            // Fix for Completed tasks - replace the message with upload form
            setTimeout(function() {
                fixCompletedTaskSubmission();
                
                // Recheck unsubmit button visibility after completed task fix
                const isLocked = viewTaskModal.dataset.isLocked === 'true';
                updateUnsubmitButtonVisibility(isLocked);
            }, 500);
        });
    }
    
    // Override fetch - this handles subsequent opens and provides data early
    const originalFetch = window.fetch;
    window.fetch = function(input, init) {
        return originalFetch(input, init).then(response => {
            // Only process responses from get_task_details.php
            if (typeof input === 'string' && input.includes('get_task_details.php')) {
                const clonedResponse = response.clone();
                
                // Process the response
                clonedResponse.json().then(data => {
                    console.log("Task lock status in fetch override:", data.is_locked);
                    
                    // Check if this task has a submission
                    if (data.has_submission) {
                        console.log("Found task with submission, will preemptively restructure");
                        
                        // Set up a MutationObserver to catch when the modal content is populated
                        const observer = new MutationObserver(function(mutations) {
                            // Try to reorganize - if successful, disconnect the observer
                            if (reorganizeSubmission()) {
                                // After reorganization, update button visibility
                                updateUnsubmitButtonVisibility(data.is_locked);
                                observer.disconnect();
                            }
                        });
                        
                        // Start observing the modal for changes
                        const taskDetailsContent = document.getElementById('taskDetailsContent');
                        if (taskDetailsContent) {
                            observer.observe(taskDetailsContent, {
                                childList: true,
                                subtree: true
                            });
                            
                            // Also try immediately in case content is already there
                            reorganizeSubmission();
                            updateUnsubmitButtonVisibility(data.is_locked);
                        }
                    }
                    
                    // Add a delay to fix Completed tasks
                    setTimeout(function() {
                        fixCompletedTaskSubmission();
                        // Reapply lock status after completed task fix
                        updateUnsubmitButtonVisibility(data.is_locked);
                    }, 500);
                });
            }
            
            return response;
        });
    };
    
    // Direct polling for any unsubmit buttons that might have been missed
    setInterval(function() {
        const modal = document.getElementById('viewTaskModal');
        if (modal && modal.classList.contains('show')) {
            const isLocked = modal.dataset.isLocked === 'true';
            if (isLocked) {
                document.querySelectorAll('a[href*="unsubmit_task.php"], .unsubmit-btn').forEach(btn => {
                    btn.style.display = 'none';
                });
            }
        }
    }, 1000);
    
    // Set interval to periodically check and fix Completed tasks
    setInterval(fixCompletedTaskSubmission, 1000);
});

// Immediately run as soon as possible
(function() {
    const style = document.createElement('style');
    style.textContent = `
        body[data-task-locked="true"] .unsubmit-btn,
        body[data-task-locked="true"] a[href*="unsubmit_task.php"],
        .task-locked .unsubmit-btn,
        .task-locked a[href*="unsubmit_task.php"] {
            display: none !important;
            visibility: hidden !important;
            pointer-events: none !important;
            opacity: 0 !important;
        }
        
        /* Hide upload form and submit button when task is locked */
        body[data-task-locked="true"] #upload-area,
        body[data-task-locked="true"] #task-submission-form,
        body[data-task-locked="true"] #task-submission-form button[type="submit"],
        .task-locked #upload-area,
        .task-locked #task-submission-form,
        .task-locked #task-submission-form button[type="submit"] {
            display: none !important;
            visibility: hidden !important;
            pointer-events: none !important;
            opacity: 0 !important;
        }
        
        /* Show locked message in place of upload form */
        body[data-task-locked="true"] .alert-warning,
        .task-locked .alert-warning {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    `;
    document.head.appendChild(style);
})();

// Also run this on load to make sure submission headers are visible
window.addEventListener('load', function() {
    // Find and remove the style that hides .submission-header elements
    const styles = document.querySelectorAll('style');
    for(let style of styles) {
        if(style.innerHTML.includes('.submission-header { display: none')) {
            console.log("Found and removed style hiding submission headers on load");
            style.remove();
        }
    }
    
    // Fix Completed tasks on load
    setTimeout(function() {
        const taskContent = document.getElementById('taskDetailsContent');
        if (taskContent) {
            const statusBadge = taskContent.querySelector('.badge.bg-completed') || 
                              taskContent.querySelector('.badge.bg-success');
            if (statusBadge) {
                const submissionSection = taskContent.querySelector('.submission-section');
                if (submissionSection) {
                    const alertMessage = submissionSection.querySelector('.alert-info');
                    if (alertMessage && alertMessage.textContent.includes('completed and you did not submit')) {
                        console.log("Found alert message for Completed task on load");
                        
                        // Get task ID and class ID
                        let taskId = '';
                        let classId = '';
                        
                        const urlParams = new URLSearchParams(window.location.search);
                        classId = urlParams.get('id') || '';
                        
                        // Replace the alert with an upload form
                        alertMessage.outerHTML = `
                            <form id="task-submission-form" action="submit_task.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="task_id" value="${taskId}">
                                <input type="hidden" name="referer" value="class_details.php?id=${classId}">
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
                        
                        // Initialize file upload handling - use the function from task_ajax.js
                        if (typeof window.initializeFileUpload === 'function') {
                            window.initializeFileUpload();
                        }
                    }
                }
            }
        }
        
        // Check if task is locked from modal data and update UI
        const modal = document.getElementById('viewTaskModal');
        if (modal && modal.dataset.isLocked === 'true') {
            document.querySelectorAll('a[href*="unsubmit_task.php"], .unsubmit-btn').forEach(btn => {
                btn.style.display = 'none';
            });
        }
    }, 1000);
});