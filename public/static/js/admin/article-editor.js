// 文章编辑器初始化和相关功能
(function() {
    var adminBase = window.ADMIN_PATH || '/admin';
    // 网站根目录是 /public，所以静态资源路径应该是 /static/...
    const wangEditorPath = '/static/wangeditor/cdn/wangEditor.min.js';

    // 检查 wangEditor 是否已加载
    if (typeof window.wangEditor !== 'undefined' || typeof window.E !== 'undefined') {
        console.log('wangEditor 已加载，直接初始化');
        initEditor();
        return;
    }

    // 使用本地文件
    const script = document.createElement('script');
    script.src = wangEditorPath;
    script.onload = function() {
        console.log('wangEditor 加载成功，开始初始化');
        initEditor();
    };
    script.onerror = function() {
        console.error('本地 wangEditor 文件加载失败');
        alert('编辑器加载失败，请刷新页面重试');
    };
    document.head.appendChild(script);

    function initEditor() {
        console.log('尝试初始化编辑器...');

        // wangeditor 4.x 版本的 API
        const E = window.wangEditor || window.E;
        if (!E) {
            console.error('wangEditor 未正确加载');
            return;
        }

        // 从DOM中获取CSRF Token
        const csrfTokenElement = document.getElementById('csrf_token');
        const csrfToken = csrfTokenElement ? csrfTokenElement.value : '';

        // 获取初始内容：优先隐藏的 textarea（后端填充），否则容器占位内容
        const editorContainer = document.getElementById('editor');
        const contentTextarea = document.getElementById('content');
        let initialHtml = '';
        if (contentTextarea && typeof contentTextarea.value === 'string') {
            initialHtml = contentTextarea.value.trim();
        } else if (editorContainer && typeof editorContainer.innerHTML === 'string') {
            initialHtml = editorContainer.innerHTML.trim();
        }

        // 清空编辑器容器（移除模板内容）
        if (editorContainer) {
            editorContainer.innerHTML = '';
        }

        // wangeditor 4.x 版本：创建编辑器实例
        // 参数：toolbar容器, editor容器
        const editor = new E('#toolbar', '#editor');

        // 配置编辑器
        editor.config.placeholder = '请输入文章内容...';

        // 配置图片上传
        editor.config.uploadImgServer = adminBase + '/upload/upload';
        // 避免触发 nginx 413，前端限制到 1MB
        editor.config.uploadImgMaxSize = 1 * 1024 * 1024; // 1M
        editor.config.uploadImgMaxLength = 5; // 一次最多上传5张图片
        editor.config.uploadImgTimeout = 30000; // 30秒超时
        editor.config.uploadFileName = 'file';
        editor.config.uploadImgHeaders = {
            'X-Requested-With': 'XMLHttpRequest'
        };
        editor.config.uploadImgParams = {
            csrf_token: csrfToken,
            module: 'article'
        };
        editor.config.uploadImgHooks = {
            fail: function (xhr, editor, resData) {
                alert((resData && resData.message) ? resData.message : '上传失败，请检查图片大小（≤1MB）');
            },
            error: function (xhr, editor) {
                if (xhr && xhr.status === 413) {
                    alert('图片过大，服务器拒绝上传（413）。请压缩到 1MB 内后重试。');
                }
            }
        };

        // 如果编辑时已有内容，设置初始内容
        if (initialHtml) {
            editor.config.onchange = function(html) {
                // 内容变化时的回调
            };
        }

        // 创建编辑器
        editor.create();

        // 如果编辑时已有内容，设置内容
        if (initialHtml) {
            editor.txt.html(initialHtml);
        }

        // 表单提交处理（使用 AJAX 提交，支持自动跳转）
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // 阻止默认提交

                try {
                    // 同步编辑器内容到隐藏的textarea
                    const content = editor.txt.html();
                    const contentTextarea = document.getElementById('content');
                    if (contentTextarea) {
                        contentTextarea.value = content || '';
                    } else {
                        console.error('找不到 content textarea 元素');
                    }

                    // 创建 FormData
                    const formData = new FormData(form);

                    // 显示提交中状态
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn ? submitBtn.innerHTML : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
                    }

                    // 发送 AJAX 请求
                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(async function(response) {
                        const text = await response.text();
                        try {
                            return JSON.parse(text);
                        } catch (err) {
                            throw new Error(text.slice(0, 200) || '服务器返回非JSON');
                        }
                    })
                    .then(function(data) {
                        if (data.code === 0) {
                            // 保存成功，显示提示并跳转
                            if (data.message) {
                                alert(data.message);
                            }
                            // 跳转到指定URL或文章列表
                            window.location.href = data.url || (adminBase + '/article');
                        } else {
                            // 保存失败，显示错误信息
                            alert(data.message || '保存失败');
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            }
                        }
                    })
                    .catch(function(error) {
                        console.error('保存失败:', error);
                        alert('保存失败，请重试');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    });
                } catch (error) {
                    console.error('表单提交错误:', error);
                    alert('提交失败: ' + error.message);
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                }
            });
        }

        // 图片选择函数（用于封面图片上传）
        window.selectImage = function() {
            // 创建文件输入元素
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.style.display = 'none';

            input.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;

                // 验证文件类型
                if (!file.type.match(/^image\/(jpeg|jpg|png|gif|webp)$/)) {
                    alert('请选择图片文件（jpg、png、gif、webp）');
                    return;
                }

                // 验证文件大小（最大 5MB）
                if (file.size > 5 * 1024 * 1024) {
                    alert('图片大小不能超过 5MB');
                    return;
                }

                // 创建 FormData
                const formData = new FormData();
                formData.append('file', file);
                const csrfToken = document.getElementById('csrf_token')?.value || '';
                formData.append('csrf_token', csrfToken);
                formData.append('module', 'article');

                // 如果是编辑模式，添加更新内容表的参数
                const articleId = document.querySelector('input[name="id"]');
                if (articleId && articleId.value) {
                    formData.append('update_table', 'sys_content');
                    formData.append('update_field', 'cover_img');
                    formData.append('update_id', articleId.value);
                }

                // 显示上传中提示
                const coverImgInput = document.getElementById('cover_img');
                const originalValue = coverImgInput.value;
                coverImgInput.value = '上传中...';
                coverImgInput.disabled = true;

                // 上传文件
                fetch(adminBase + '/upload/upload', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (err) {
                        throw new Error(text.slice(0, 200) || '服务器返回非JSON');
                    }
                })
                .then(data => {
                    coverImgInput.disabled = false;
                    if (data.errno === 0 && data.data && data.data.length > 0) {
                        // 上传成功，设置图片URL
                        coverImgInput.value = data.data[0];
                        // 显示预览
                        const preview = coverImgInput.parentElement.nextElementSibling;
                        if (preview && preview.classList.contains('mt-2')) {
                            preview.innerHTML = '<img src="' + data.data[0] + '" width="200" class="img-thumbnail">';
                        } else {
                            const previewDiv = document.createElement('div');
                            previewDiv.className = 'mt-2';
                            previewDiv.innerHTML = '<img src="' + data.data[0] + '" width="200" class="img-thumbnail">';
                            coverImgInput.parentElement.parentElement.appendChild(previewDiv);
                        }
                    } else {
                        // 上传失败
                        coverImgInput.value = originalValue;
                        alert(data.message || '上传失败');
                    }
                })
                .catch(error => {
                    coverImgInput.disabled = false;
                    coverImgInput.value = originalValue;
                    console.error('上传错误:', error);
                    alert('上传失败，请重试');
                });
            };

            // 触发文件选择
            document.body.appendChild(input);
            input.click();
            document.body.removeChild(input);
        };

        // 删除封面图片函数
        window.deleteCoverImage = function(triggerBtn) {
            if (!confirm('确定要删除当前封面图片吗？此操作不可撤销！')) {
                return;
            }

            const coverImgInput = document.getElementById('cover_img');
            const imageUrl = coverImgInput.value;
            if (!imageUrl) {
                alert('没有可删除的封面图片');
                return;
            }

            // 显示删除中状态
            const deleteBtn = triggerBtn || null;
            const originalText = deleteBtn ? deleteBtn.innerHTML : '';
            if (deleteBtn) {
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '删除中...';
            }

            // 准备删除请求数据
            const formData = new FormData();
            formData.append('image_url', imageUrl);
            const csrfToken = document.getElementById('csrf_token')?.value || '';
            formData.append('csrf_token', csrfToken);

            // 发送删除请求
            fetch(adminBase + '/upload/deleteCoverImage', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                }
                if (data.code === 0) {
                    // 删除成功，清空封面图片输入框和预览
                    coverImgInput.value = '';
                    const preview = coverImgInput.parentElement.nextElementSibling;
                    if (preview && preview.classList.contains('mt-2')) {
                        preview.innerHTML = '';
                    }
                    alert(data.message || '封面图片删除成功');
                } else {
                    // 删除失败
                    alert(data.message || '封面图片删除失败');
                }
            })
            .catch(error => {
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                }
                console.error('删除错误:', error);
                alert('封面图片删除失败，请重试');
            });
        };

        // 绑定封面图片相关按钮（避免 inline handler 触发 CSP）
        const selectCoverBtn = document.getElementById('btnSelectCover');
        if (selectCoverBtn) {
            selectCoverBtn.addEventListener('click', function (event) {
                event.preventDefault();
                window.selectImage();
            });
        }

        const cropCoverBtn = document.getElementById('cropButton');
        if (cropCoverBtn) {
            cropCoverBtn.addEventListener('click', function (event) {
                event.preventDefault();
                if (typeof window.selectImageWithCrop === 'function') {
                    window.selectImageWithCrop('cover_img');
                }
            });
        }

        const deleteCoverBtn = document.getElementById('btnDeleteCover');
        if (deleteCoverBtn) {
            deleteCoverBtn.addEventListener('click', function (event) {
                event.preventDefault();
                window.deleteCoverImage(deleteCoverBtn);
            });
        }
    }
})();
