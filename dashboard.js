function showProjectForm() {
    const form = document.getElementById('projectForm');
    if (form) {
        form.reset();
        document.getElementById('project_id').value = '';

        // Clear errors
        clearDashboardErrors();

        // Clear custom multi-select
        const select = document.getElementById("technologies");
        if (select) {
            Array.from(select.options).forEach(opt => opt.selected = false);
            // Re-render empty tags
            const tagsContainer = document.querySelector(".selected-tags");
            const optionsList = document.querySelector(".multi-select-options");
            if (tagsContainer) tagsContainer.innerHTML = '';
            if (optionsList) {
                Array.from(optionsList.children).forEach(div => div.classList.remove("selected"));
            }
        }

        // Clear file previews
        const preview = document.getElementById("filePreview");
        if (preview) preview.innerHTML = '';
        selectedFiles = []; // Reset global tracking array

        // Remove existing files section (from previous edit)
        const existWrapper = document.querySelector(".existing-files");
        if (existWrapper) existWrapper.remove();

        const removeContainer = document.getElementById("removeFilesContainer");
        if (removeContainer) removeContainer.innerHTML = '';
    }
    document.getElementById('projectFormContainer').style.display = 'block';
}

function hideProjectForm() {
    document.getElementById('projectFormContainer').style.display = 'none';
}

function editProject(id) {
    alert("Editing project id: " + id + " (Add AJAX prefill logic here)");
}

/* ===============================
   MULTIPLE FILE UPLOAD PREVIEW & REMOVE
=============================== */
const fileInput = document.getElementById("project_files");
const filePreview = document.getElementById("filePreview");
let selectedFiles = [];

if (fileInput && filePreview) {
    fileInput.addEventListener("change", e => {
        const newFiles = Array.from(e.target.files);

        // Check Limit
        if (selectedFiles.length + newFiles.length > 5) {
            alert("Maximum 5 files allowed per project.");
            e.target.value = ''; // Reset input
            return;
        }

        // Accumulate files (avoid duplicates)
        newFiles.forEach(file => {
            const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
            if (!exists) {
                if (selectedFiles.length < 5) {
                    selectedFiles.push(file);
                }
            }
        });

        // Update the input field with the new total list
        updateFileInput();

        // Render preview
        renderFilePreview();
    });

    filePreview.addEventListener("click", e => {
        if (e.target.classList.contains("remove-file")) {
            const idx = parseInt(e.target.dataset.index, 10);
            if (Number.isNaN(idx)) return;

            // Remove from array
            selectedFiles.splice(idx, 1);

            // Update input
            updateFileInput();

            // Re-render
            renderFilePreview();
        }
    });

    function updateFileInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
    }

    function renderFilePreview() {
        filePreview.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const div = document.createElement("div");
            div.className = "preview-item";

            // Check file type for preview
            let content = '';
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.alt = file.name;
                    div.insertBefore(img, div.firstChild);
                };
                reader.readAsDataURL(file);
                // Placeholder while loading
                content = `<div class="loading-placeholder">...</div>`;
            } else {
                // Icons based on extension
                const ext = file.name.split('.').pop().toLowerCase();
                let icon = '📄'; // Default
                let color = '#6b7280';

                if (ext === 'pdf') { icon = '📄'; color = '#ef4444'; } // PDF Icon
                else if (['xls', 'xlsx', 'csv'].includes(ext)) { icon = '📊'; color = '#10b981'; } // Excel Icon
                else if (['doc', 'docx'].includes(ext)) { icon = '📝'; color = '#3b82f6'; } // Word Icon
                else if (['zip', 'rar'].includes(ext)) { icon = '📦'; color = '#f59e0b'; } // Zip Icon

                content = `<div class="file-icon" style="color:${color}">${icon}</div>`;
            }

            div.innerHTML = `
                ${content}
                <span title="${file.name}">${file.name}</span>
                <button type="button" class="remove-file" data-index="${index}">&times;</button>
            `;
            filePreview.appendChild(div);
        });
    }
}

/* ===============================
   REMOVE EXISTING FILES (EDIT MODE) - BUTTON UX
=============================== */
const removeFilesContainer = document.getElementById("removeFilesContainer");
const existingFilesWrapper = document.querySelector(".existing-files");

if (existingFilesWrapper && removeFilesContainer) {
    existingFilesWrapper.addEventListener("click", function (e) {
        if (e.target.classList.contains("remove-existing-file")) {
            const fileId = e.target.getAttribute("data-file-id");
            const item = e.target.closest(".existing-file-item");
            if (!fileId || !item) return;

            // Add a hidden input so backend receives remove_files[]
            const hidden = document.createElement("input");
            hidden.type = "hidden";
            hidden.name = "remove_files[]";
            hidden.value = fileId;
            removeFilesContainer.appendChild(hidden);

            // Remove from UI immediately
            item.remove();
        }
    });
}

/* ===============================
   SEARCHABLE MULTI-SELECT
=============================== */
document.addEventListener("DOMContentLoaded", () => {
    initMultiSelect();
});

function initMultiSelect() {
    const select = document.getElementById("technologies");
    if (!select) return;

    // Hide original select
    select.classList.add("hidden-select");

    // Create Custom UI
    const container = document.createElement("div");
    container.className = "multi-select-container";

    const tagsContainer = document.createElement("div");
    tagsContainer.className = "selected-tags";

    const searchInput = document.createElement("input");
    searchInput.type = "text";
    searchInput.placeholder = "Search technologies...";
    searchInput.className = "multi-select-search";

    const optionsList = document.createElement("div");
    optionsList.className = "multi-select-options";

    // Populate Options
    Array.from(select.options).forEach(opt => {
        const div = document.createElement("div");
        div.className = "multi-select-option";
        div.textContent = opt.text;
        div.dataset.value = opt.value;
        if (opt.selected) div.classList.add("selected");

        div.addEventListener("click", () => toggleSelection(opt.value, div));
        optionsList.appendChild(div);
    });

    container.appendChild(tagsContainer);
    container.appendChild(searchInput);
    container.appendChild(optionsList);

    // Insert after original select
    select.parentNode.insertBefore(container, select.nextSibling);

    // Initial render of selected tags
    renderTags();

    // Event Listeners
    searchInput.addEventListener("focus", () => optionsList.classList.add("show"));

    // Filter logic
    searchInput.addEventListener("input", (e) => {
        const val = e.target.value.toLowerCase();
        Array.from(optionsList.children).forEach(optDiv => {
            const text = optDiv.textContent.toLowerCase();
            optDiv.style.display = text.includes(val) ? "block" : "none";
        });
        optionsList.classList.add("show");
    });

    // Close on click outside
    document.addEventListener("click", (e) => {
        if (!container.contains(e.target)) {
            optionsList.classList.remove("show");
        }
    });

    function toggleSelection(value, div) {
        const option = select.querySelector(`option[value="${value}"]`);
        option.selected = !option.selected; // Toggle

        if (option.selected) {
            div.classList.add("selected");
        } else {
            div.classList.remove("selected");
        }
        renderTags();
        searchInput.value = ""; // Clear search after selection
        searchInput.focus();

        // Reset filter
        Array.from(optionsList.children).forEach(d => d.style.display = "block");
    }

    function renderTags() {
        tagsContainer.innerHTML = "";
        Array.from(select.selectedOptions).forEach(opt => {
            const tag = document.createElement("div");
            tag.className = "tag";
            tag.innerHTML = `
                ${opt.text} 
                <span class="remove-tag" data-value="${opt.value}">&times;</span>
            `;
            tag.querySelector(".remove-tag").addEventListener("click", (e) => {
                e.stopPropagation();
                toggleSelection(opt.value, optionsList.querySelector(`[data-value="${opt.value}"]`));
            });
            tagsContainer.appendChild(tag);
        });
    }
}

/* ===============================
   INLINE VALIDATION FOR PROJECT FORM
=============================== */
const projectForm = document.getElementById("projectForm");

function clearDashboardErrors() {
    document.querySelectorAll("#projectForm .error-msg").forEach(el => el.textContent = "");
    document.querySelectorAll("#projectForm .field-error").forEach(el => el.classList.remove("field-error"));
    // Clear custom select error style if any
    const multiContainer = document.querySelector(".multi-select-container");
    if (multiContainer) multiContainer.style.borderColor = "#d1d5db";
}

if (projectForm) {
    projectForm.addEventListener("submit", function (e) {
        clearDashboardErrors();
        let valid = true;

        const title = document.getElementById("title");
        const description = document.getElementById("description");
        const technologies = document.getElementById("technologies");

        if (!title.value.trim()) {
            document.getElementById("titleError").textContent = "Title is required.";
            title.classList.add("field-error");
            valid = false;
        }

        if (!description.value.trim()) {
            document.getElementById("descriptionError").textContent = "Description is required.";
            description.classList.add("field-error");
            valid = false;
        }

        if (!technologies.selectedOptions || technologies.selectedOptions.length === 0) {
            document.getElementById("technologiesError").textContent = "Select at least one technology.";
            const multiContainer = document.querySelector(".multi-select-container");
            if (multiContainer) multiContainer.style.borderColor = "#dc2626";
            valid = false;
        }

        // Check Total File Size (approx 35MB limit to stay under 40MB post_max_size safe zone)
        const MAX_SIZE_MB = 35;
        const MAX_BYTES = MAX_SIZE_MB * 1024 * 1024;
        let totalSize = 0;

        // Count selected files
        selectedFiles.forEach(f => totalSize += f.size);

        // Count existing files if needed? (Usually existing don't count towards POST size unless re-uploaded, which they aren't)
        // But the error was POST content length, which comes from new uploads.

        if (totalSize > MAX_BYTES) {
            document.getElementById("projectFilesError").textContent = `Total file size (${(totalSize / 1024 / 1024).toFixed(2)}MB) exceeds the limit of ${MAX_SIZE_MB}MB.`;
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
        }
    });
}

/* ===============================
   PROFILE DROPDOWN
=============================== */
function toggleProfileDropdown() {
    const dropdown = document.getElementById("profileDropdown");
    dropdown.classList.toggle("show");
}

// Close dropdown when clicking outside
window.addEventListener("click", function (e) {
    if (!e.target.closest('.profile-wrapper')) {
        const dropdown = document.getElementById("profileDropdown");
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
});

/* ===============================
   INLINE DELETE CONFIRMATION
=============================== */
document.querySelectorAll(".delete-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const id = btn.dataset.id;
        document.getElementById(`delete-confirm-${id}`).style.display = 'inline-flex';
    });
});

function confirmDeleteJS(id) {
    window.location.href = "dashboard.php?delete=" + id;
}

function cancelDelete(id) {
    document.getElementById(`delete-confirm-${id}`).style.display = 'none';
}
