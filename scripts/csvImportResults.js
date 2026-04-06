document.addEventListener("DOMContentLoaded", function() {
    var config = window.csvImportPluginConfig;
    if (!config) return;

    var currentUuid = null;

    var importingLabel = config.labels.importing || "Importing";
    var importedLabel = config.labels.imported || "Imported";
    var savingLabel = pkp.localeKeys["common.saving"] || "Saving";
    var savedLabel = pkp.localeKeys["form.saved"] || "Saved";

    var statusObserver = new MutationObserver(function() {
        var csvTab = document.getElementById("csvImportPlugin");
        if (!csvTab) return;

        var statusSpans = csvTab.querySelectorAll(".pkpFormPage__status");
        statusSpans.forEach(function(span) {
            span.childNodes.forEach(function(node) {
                if (node.nodeType !== 3) return;
                var text = node.textContent.trim();
                if (text === savingLabel) {
                    node.textContent = " " + importingLabel;
                } else if (text === savedLabel) {
                    node.textContent = " " + importedLabel;
                }
            });
        });
    });
    statusObserver.observe(document.body, { childList: true, subtree: true, characterData: true });

    pkp.eventBus.$on("form-success", function(fId, response) {
        if (fId !== config.formId) return;

        currentUuid = response.uuid || null;

        var labels = config.labels;
        var downloadBaseUrl = config.downloadBaseUrl;
        var bodyHtml = buildModalContent(response, labels, downloadBaseUrl);
        var title = response.resultDryMode ? labels.dryModeTitle : labels.importCompleteTitle;
        var accentColor = response.resultFailedRows > 0 ? "#D00A6C" : "#00B24E";

        showModal(title, bodyHtml, accentColor, function() {
            callCleanup(currentUuid);
            currentUuid = null;
        });
    });

    function buildModalContent(response, labels, downloadBaseUrl) {
        var html = "";

        html += "<div style='display:flex; gap:1.5rem; flex-wrap:wrap; margin-bottom:1.5rem; padding:1rem; background:rgba(234,237,238,0.3); border:1px solid #BBBBBB; border-radius:4px;'>";
        html += summaryBadge(labels.filesProcessed, response.resultFilesProcessed, "#222222");
        html += summaryBadge(labels.totalRows, response.resultTotalRows, "#222222");
        html += summaryBadge(labels.successfulRows, response.resultSuccessfulRows, "#00B24E");
        html += summaryBadge(labels.failedRows, response.resultFailedRows, response.resultFailedRows > 0 ? "#D00A6C" : "#222222");
        html += "</div>";

        if (response.resultPerFile && response.resultPerFile.length > 0) {
            for (var i = 0; i < response.resultPerFile.length; i++) {
                html += buildFileSection(response.resultPerFile[i], response.uuid, downloadBaseUrl, labels);
            }
        }

        return html;
    }

    function summaryBadge(label, value, color) {
        return "<div style='text-align:center; flex:1; min-width:80px;'>" +
            "<div style='font-size:1.5rem; font-weight:700; color:" + color + "; line-height:2rem;'>" + value + "</div>" +
            "<div style='font-size:0.75rem; font-weight:400; color:#505050; line-height:1rem;'>" + label + "</div>" +
            "</div>";
    }

    function buildFileSection(file, uuid, downloadBaseUrl, labels) {
        var html = "";

        html += "<div style='margin-top:1rem; padding:0.5rem 0.75rem; background:#002C40; color:#FFFFFF; border-radius:4px 4px 0 0; font-family:monospace; font-size:0.875rem; font-weight:700;'>";
        html += "=== " + escapeHtml(file.filename) + " ===";
        html += "</div>";

        if (file.errors && file.errors.length > 0) {
            html += "<div style='border:1px solid #BBBBBB; border-top:none; overflow-x:auto;'>";
            html += "<table style='width:100%; border-collapse:separate; border-spacing:0; font-family:monospace; font-size:0.75rem;'>";
            html += "<thead><tr style='background:#FFFFFF;'>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; width:60px; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>ROW</th>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; width:80px; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>STATUS</th>";
            html += "<th style='padding:0.5rem 0.75rem; text-align:left; border-bottom:2px solid #BBBBBB; color:#01354F; font-weight:700;'>ERROR</th>";
            html += "</tr></thead><tbody>";

            for (var j = 0; j < file.errors.length; j++) {
                var err = file.errors[j];
                var rowBg = j % 2 === 1 ? "background:rgba(234,237,238,0.3);" : "";
                html += "<tr style='" + rowBg + "'>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#505050;'>" + err.row + "</td>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#D00A6C; font-weight:700;'>FAILED</td>";
                html += "<td style='padding:0.375rem 0.75rem; border-bottom:1px solid #BBBBBB; color:#222222;'>" + escapeHtml(err.reason) + "</td>";
                html += "</tr>";
            }

            html += "</tbody></table>";
            html += "</div>";
        }

        var passed = file.successful;
        var failed = file.failed;
        var total = file.rows;
        html += "<div style='padding:0.5rem 0.75rem; font-family:monospace; font-size:0.75rem; border:1px solid #BBBBBB; border-top:" + (file.errors && file.errors.length > 0 ? "none" : "1px solid #BBBBBB") + "; border-radius:0 0 4px 4px; background:#FFFFFF;'>";
        html += "Result: <span style='color:#00B24E; font-weight:700;'>" + passed + " passed</span>, ";
        html += "<span style='color:" + (failed > 0 ? "#D00A6C" : "#222222") + "; font-weight:700;'>" + failed + " failed</span>";
        html += " (" + total + " total)";
        html += "</div>";

        if (file.invalidFile) {
            var sep = downloadBaseUrl.indexOf("?") === -1 ? "?" : "&";
            var url = downloadBaseUrl + sep + "uuid=" + encodeURIComponent(uuid) + "&filename=" + encodeURIComponent(file.invalidFile);
            html += "<div style='margin-top:0.25rem; padding:0.25rem 0.75rem;'>";
            html += "<a href='" + url + "' style='color:#006798; font-size:0.875rem; text-decoration:none;'>" + labels.invalidFiles + ": " + escapeHtml(file.invalidFile) + "</a>";
            html += "</div>";
        }

        return html;
    }

    function escapeHtml(str) {
        var div = document.createElement("div");
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function callCleanup(uuid) {
        if (!uuid || !config.cleanupUrl) return;

        $.ajax({
            method: "POST",
            url: config.cleanupUrl,
            headers: { "X-Csrf-Token": pkp.currentUser.csrfToken },
            data: { uuid: uuid }
        });
    }

    function showModal(title, bodyHtml, accentColor, onClose) {
        var existing = document.getElementById("csvImportModal");
        if (existing) existing.remove();

        var overlay = document.createElement("div");
        overlay.id = "csvImportModal";
        overlay.style.cssText = "position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 300ms ease-out;";

        var modal = document.createElement("div");
        modal.style.cssText = "background:#FFFFFF; border-radius:4px; max-width:48rem; width:90%; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 0 4px rgba(0,0,0,0.5); border-left:14px solid " + accentColor + "; transform:scale(0.95); opacity:0; transition:all 300ms ease-out; pointer-events:auto;";

        var header = document.createElement("div");
        header.style.cssText = "padding:3rem 2rem 2rem 2rem; flex-shrink:0; position:relative;";

        var titleEl = document.createElement("h2");
        titleEl.textContent = title;
        titleEl.style.cssText = "margin:0; font-size:1.5rem; font-weight:700; line-height:2rem; color:#01354F; font-family:'Noto Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;";
        header.appendChild(titleEl);

        var closeBtn = document.createElement("button");
        closeBtn.innerHTML = "&#215;";
        closeBtn.style.cssText = "position:absolute; right:0.75rem; top:0.75rem; width:1.5rem; height:1.5rem; background:none; border:none; font-size:1.25rem; cursor:pointer; color:#D00A6C; display:flex; align-items:center; justify-content:center; border-radius:4px; padding:0;";
        closeBtn.onmouseenter = function() { this.style.background = "rgba(208,10,108,0.1)"; };
        closeBtn.onmouseleave = function() { this.style.background = "none"; };
        closeBtn.onclick = function() { closeModal(overlay, modal, onClose); };
        header.appendChild(closeBtn);

        var body = document.createElement("div");
        body.style.cssText = "padding:0 2rem; overflow-y:auto; flex:1; font-family:'Noto Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; font-size:0.875rem; color:#222222; line-height:1.25rem;";
        body.innerHTML = bodyHtml;

        var footer = document.createElement("div");
        footer.style.cssText = "padding:1.5rem 2rem; border-top:1px solid #BBBBBB; text-align:right; flex-shrink:0;";

        var closeFooterBtn = document.createElement("button");
        closeFooterBtn.textContent = "Close";
        closeFooterBtn.style.cssText = "padding:0 12px; background:#006798; color:#FFFFFF; border:1px solid transparent; border-radius:4px; cursor:pointer; font-size:0.875rem; font-weight:600; font-family:'Noto Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; display:inline-flex; align-items:center;";
        closeFooterBtn.onmouseenter = function() { this.style.background = "#0082BF"; };
        closeFooterBtn.onmouseleave = function() { this.style.background = "#006798"; };
        closeFooterBtn.onclick = function() { closeModal(overlay, modal, onClose); };
        footer.appendChild(closeFooterBtn);

        modal.appendChild(header);
        modal.appendChild(body);
        modal.appendChild(footer);
        overlay.appendChild(modal);

        overlay.addEventListener("click", function(e) {
            if (e.target === overlay) closeModal(overlay, modal, onClose);
        });

        var escHandler = function(e) {
            if (e.key === "Escape") {
                closeModal(overlay, modal, onClose);
                document.removeEventListener("keydown", escHandler);
            }
        };
        document.addEventListener("keydown", escHandler);

        document.body.appendChild(overlay);

        requestAnimationFrame(function() {
            overlay.style.opacity = "1";
            modal.style.transform = "scale(1)";
            modal.style.opacity = "1";
        });
    }

    function closeModal(overlay, modal, onClose) {
        overlay.style.transition = "opacity 200ms ease-in";
        overlay.style.opacity = "0";
        modal.style.transition = "all 200ms ease-in";
        modal.style.transform = "scale(0.95)";
        modal.style.opacity = "0";

        if (typeof onClose === "function") {
            onClose();
        }

        setTimeout(function() {
            overlay.remove();
        }, 200);
    }
});
