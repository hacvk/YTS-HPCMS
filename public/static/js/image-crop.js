// Simplified & refactored image cropping logic – no fixed container sizing
// Author: Cascade AI – 2024-06
// -------------------------------------------------------------------
// 依赖: Bootstrap 5 modal, Cropper.js 1.x
// HTML 结构要求见 views/admin/article/edit.latte
// -------------------------------------------------------------------

(function () {
    "use strict";

    let cropper = null;          // Cropper 实例
    let currentTargetId = null;  // 目标 input 的 id
    let selectedAspectRatio = NaN; // 记录用户选中的比例(无 cropper 时暂存)

    // 工具: 创建 / 获取 Bootstrap Modal 实例
    function getModal() {
        const el = document.getElementById("cropperModal");
        if (!el) return null;
        return bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    }

    /* --------------------  DOM 事件绑定 -------------------- */
    document.addEventListener("DOMContentLoaded", () => {
        const modalEl = document.getElementById("cropperModal");
        if (!modalEl) return;

        const aspectRatioSelect = document.getElementById("aspectRatioSelect");
        const lockChk          = document.getElementById("lockAspectRatio");

        // 监听预设比例选择
        if (aspectRatioSelect) {
            aspectRatioSelect.addEventListener("change", () => {
                const v = parseFloat(aspectRatioSelect.value) || NaN;
                selectedAspectRatio = v;
                // 如果 cropper 已存在立即应用
                if (cropper) cropper.setAspectRatio(isNaN(v)? NaN : v);
                lockChk.checked = !isNaN(v);
            });
        }

        // 锁定比例开关
        if (lockChk) {
            lockChk.addEventListener("change", () => {
                if (!cropper) return;
                if (!lockChk.checked) {
                    cropper.setAspectRatio(NaN);
                    aspectRatioSelect.value = "0";
                    selectedAspectRatio = NaN;
                } else if (!isNaN(selectedAspectRatio)) {
                    cropper.setAspectRatio(selectedAspectRatio);
                } else {
                    const w = parseInt(document.getElementById("targetWidth").value)||0;
                    const h = parseInt(document.getElementById("targetHeight").value)||0;
                    if (w>0 && h>0) {
                        const ratio = w/h;
                        cropper.setAspectRatio(ratio);
                        selectedAspectRatio = ratio;
                    }
                }
            });
        }

        /* ---------- Modal show / hide ---------- */
        modalEl.addEventListener("shown.bs.modal", () => {
            setTimeout(() => {
                const img = document.getElementById("cropperImage");
                if (!img || cropper) return; // already initialised

                const aspectRatio = !isNaN(selectedAspectRatio) ? selectedAspectRatio : NaN;
                if (lockChk) lockChk.checked = !isNaN(aspectRatio);

                cropper = new Cropper(img, {
                    viewMode: 1,
                    aspectRatio,
                    autoCropArea: 0.9,
                    background: true,
                    responsive: true,
                    preview: ".preview-lg"
                });
            }, 120); // 延迟确保容器尺寸稳定
        });

        modalEl.addEventListener("hidden.bs.modal", () => {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            document.getElementById("cropperImage").src = "";
        });

        /* ---------- 确认裁剪按钮 ---------- */
        const btn = document.getElementById("cropConfirmBtn");
        if (btn) {
            btn.addEventListener("click", async () => {
                if (!cropper) {
                    alert("裁剪器未就绪");
                    return;
                }
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 处理中...';

                try {
                    const canvas = cropper.getCroppedCanvas({ fillColor: "#fff" });
                    const dataURL = canvas.toDataURL("image/jpeg", 0.9);

                    const fd = new FormData();
                    fd.append("image_data", dataURL);
                    fd.append("x", 0);
                    fd.append("y", 0);
                    fd.append("width", canvas.width);
                    fd.append("height", canvas.height);

                    fd.append("target_width", parseInt(document.getElementById("targetWidth").value) || 0);
                    fd.append("target_height", parseInt(document.getElementById("targetHeight").value) || 0);
                    fd.append("csrf_token", document.getElementById("csrf_token").value);

                    const resp = await fetch("/admin/upload/uploadCrop", { method: "POST", body: fd });
                    const json = await resp.json();

                    if (json.code === 0 && json.data && json.data.url) {
                        const inputEl = document.getElementById(currentTargetId);
                        if (inputEl) inputEl.value = json.data.url;
                        const preview = document.getElementById(currentTargetId + "_preview");
                        if (preview) preview.innerHTML = `<img src="${json.data.url}" width="200" class="img-thumbnail">`;
                        getModal().hide();
                    } else {
                        alert(json.message || "裁剪失败");
                    }
                } catch (err) {
                    console.error(err);
                    alert("上传失败: " + err.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = "确认裁剪";
                }
            });
        }
    });

    /* -------------------- 外部可调用 -------------------- */

    window.selectImageWithCrop = function (inputId = "cover_img", opt = { width: 0, height: 0, locked: false }) {
        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.accept = "image/*";
        fileInput.style.display = "none";
        document.body.appendChild(fileInput);

        fileInput.addEventListener("change", () => {
            const file = fileInput.files && fileInput.files[0];
            document.body.removeChild(fileInput);
            if (!file) return;
            if (!/^image\//.test(file.type)) { alert("请选择图片文件"); return; }
            if (file.size > 10*1024*1024) { alert("图片大小不能超过 10MB"); return; }

            currentTargetId = inputId;
            // 更新目标尺寸和锁定
            document.getElementById("targetWidth").value = opt.width || 0;
            document.getElementById("targetHeight").value = opt.height || 0;
            document.getElementById("lockAspectRatio").checked = !!opt.locked;
            document.getElementById("aspectRatioSelect").value = "0";
            selectedAspectRatio = NaN;

            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById("cropperImage").src = e.target.result;
                getModal().show();
            };
            reader.readAsDataURL(file);
        });
        fileInput.click();
    };

    window.zoomCropper   = d   => cropper && cropper.zoom(d);
    window.rotateCropper = deg => cropper && cropper.rotate(deg);
    window.resetCropper  = ()  => cropper && cropper.reset();

})();
