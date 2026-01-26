// 扩展字段功能
(function() {
    var adminBase = window.ADMIN_PATH || '/admin';
    // 动态加载扩展字段（根据栏目类型）
    const categorySelect = document.getElementById('category_id');
    const extendFieldsContainer = document.getElementById('extend-fields');
    const articleData = JSON.parse(document.getElementById('article_json')?.value || 'null');

    // 加载扩展字段的函数
    function loadExtendFields() {
        if (!categorySelect || !extendFieldsContainer) {
            return;
        }

        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            extendFieldsContainer.innerHTML = '';
            return;
        }

        const categoryType = selectedOption.getAttribute('data-type') || 'article';

        // 调用后端接口获取字段配置
        fetch(adminBase + '/category/getModelFields?type=' + encodeURIComponent(categoryType))
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.code === 0 && data.data && Object.keys(data.data).length > 0) {
                    // 清空容器
                    extendFieldsContainer.innerHTML = '';

                    // 生成字段HTML
                    const fields = data.data;
                    const fieldPromises = [];

                    for (const fieldName in fields) {
                        if (fields.hasOwnProperty(fieldName)) {
                            const field = fields[fieldName];

                            // 如果字段有lookup_category属性，需要先获取选项
                            if (field.lookup_category) {
                                fieldPromises.push(
                                    loadCategoryOptions(field.lookup_category).then(function(options) {
                                        field.options = options;
                                        const result = {};
                                        result.fieldName = fieldName;
                                        result.field = field;
                                        return result;
                                    })
                                );
                            } else {
                                const result = {};
                                result.fieldName = fieldName;
                                result.field = field;
                                fieldPromises.push(Promise.resolve(result));
                            }
                        }
                    }

                    // 等待所有字段准备就绪
                    Promise.all(fieldPromises).then(function(fieldList) {
                        for (let i = 0; i < fieldList.length; i++) {
                            const item = fieldList[i];
                            const fieldHtml = generateFieldHtml(item.fieldName, item.field);
                            extendFieldsContainer.innerHTML += fieldHtml;
                        }

                        // 如果是编辑模式，填充已有数据
                        if (articleData) {
                            fillExtendFields(articleData);
                        }
                    });
                } else {
                    extendFieldsContainer.innerHTML = '';
                }
            })
            .catch(function(error) {
                console.error('加载扩展字段失败:', error);
                extendFieldsContainer.innerHTML = '';
            });
    }

    // 加载栏目选项
    function loadCategoryOptions(parentId) {
        return fetch(adminBase + '/category/getContentOptions?category_id=' + encodeURIComponent(parentId))
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.code === 0 && data.data) {
                    return data.data;
                }
                return {};
            })
            .catch(function(error) {
                console.error('加载栏目选项失败:', error);
                return {};
            });
    }

    // 生成字段HTML
    function generateFieldHtml(fieldName, field) {
        const label = field.label || fieldName;
        const type = field.type || 'text';
        const value = articleData && articleData[fieldName] ? articleData[fieldName] : '';

        let html = '<div class="form-group mb-3">';
        html += '<label for="extend_' + fieldName + '">' + label + '</label>';

        switch (type) {
            case 'textarea':
                html += '<textarea class="form-control" id="extend_' + fieldName + '" name="' + fieldName + '" rows="3">' + (value || '') + '</textarea>';
                break;
            case 'select':
                html += '<select class="form-control" id="extend_' + fieldName + '" name="' + fieldName + '">';
                html += '<option value="">请选择</option>';
                if (field.options && typeof field.options === 'object' && Object.keys(field.options).length > 0) {
                    for (const optValue in field.options) {
                        if (field.options.hasOwnProperty(optValue)) {
                            const optLabel = field.options[optValue];
                            const selected = (value == optValue) ? ' selected' : '';
                            html += '<option value="' + optValue + '"' + selected + '>' + optLabel + '</option>';
                        }
                    }
                }
                html += '</select>';
                break;
            case 'image':
                html += '<div class="input-group">';
                html += '<input type="text" class="form-control" id="extend_' + fieldName + '" name="' + fieldName + '" value="' + (value || '') + '" placeholder="请输入图片URL">';
                html += '<div class="input-group-append">';
                html += '<button type="button" class="btn btn-outline-secondary" onclick="selectExtendImage(\'extend_' + fieldName + '\')">选择图片</button>';
                html += '</div>';
                html += '</div>';
                if (value) {
                    html += '<div class="mt-2">';
                    html += '<img src="' + value + '" width="200" class="img-thumbnail" id="extend_' + fieldName + '_preview">';
                    html += '</div>';
                } else {
                    html += '<div class="mt-2" id="extend_' + fieldName + '_preview"></div>';
                }
                break;
            case 'image_crop':
                const width = field.width || '';
                const height = field.height || '';
                const locked = field.locked_aspect_ratio ? field.locked_aspect_ratio : false;
                html += '<div class="input-group">';
                html += '<input type="text" class="form-control" id="extend_' + fieldName + '" name="' + fieldName + '" value="' + (value || '') + '" placeholder="请选择或上传图片">';
                html += '<div class="input-group-append">';
                html += '<button type="button" class="btn btn-outline-secondary" onclick="selectExtendImage(\'extend_' + fieldName + '\')">选择图片</button>';
                html += '<button type="button" class="btn btn-outline-primary crop-btn" data-field="' + fieldName + '" data-width="' + width + '" data-height="' + height + '" data-locked="' + (locked ? 'true' : 'false') + '">裁剪上传</button>';
                html += '</div>';
                html += '</div>';
                if (value) {
                    html += '<div class="mt-2" id="extend_' + fieldName + '_preview">';
                    html += '<img src="' + value + '" width="' + (width || '200') + '" class="img-thumbnail">';
                    html += '</div>';
                } else {
                    html += '<div class="mt-2" id="extend_' + fieldName + '_preview"></div>';
                }
                if (field.help) {
                    html += '<small class="form-text text-muted">' + field.help + '</small>';
                }
                break;
            case 'editor':
                html += '<textarea class="form-control" id="extend_' + fieldName + '" name="' + fieldName + '" rows="5">' + (value || '') + '</textarea>';
                break;
            default: // text
                html += '<input type="text" class="form-control" id="extend_' + fieldName + '" name="' + fieldName + '" value="' + (value || '') + '" placeholder="请输入' + label + '">';
                break;
        }

        html += '</div>';
        return html;
    }

    // 填充扩展字段数据（编辑模式）
    function fillExtendFields(data) {
        if (!data) return;

        // 遍历所有扩展字段输入框并填充值
        const inputs = extendFieldsContainer.querySelectorAll('input, textarea, select');
        inputs.forEach(function(input) {
            const fieldName = input.name;
            if (data.hasOwnProperty(fieldName)) {
                if (input.tagName === 'SELECT') {
                    input.value = data[fieldName];
                } else {
                    input.value = data[fieldName] || '';
                }
            }
        });
    }

    // 选择扩展字段图片的函数
    window.selectExtendImage = function(inputId) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.style.display = 'none';

        input.onchange = function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.match(/^image\/(jpeg|jpg|png|gif|webp)$/)) {
                alert('请选择图片文件（jpg、png、gif、webp）');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('图片大小不能超过 5MB');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            const csrfToken = document.getElementById('csrf_token')?.value || '';

            // 如果是编辑模式，添加更新内容表的参数
            const articleId = document.querySelector('input[name="id"]');
            if (articleId && articleId.value) {
                formData.append('update_table', 'sys_content');
                formData.append('update_field', 'cover_img');
                formData.append('update_id', articleId.value);
            }
            formData.append('csrf_token', csrfToken);

            const targetInput = document.getElementById(inputId);
            const originalValue = targetInput.value;
            targetInput.value = '上传中...';
            targetInput.disabled = true;

            fetch(adminBase + '/upload/upload', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                targetInput.disabled = false;
                if (data.errno === 0 && data.data && data.data.length > 0) {
                    targetInput.value = data.data[0];
                    const previewId = inputId + '_preview';
                    const preview = document.getElementById(previewId);
                    if (preview) {
                        preview.innerHTML = '<img src="' + data.data[0] + '" width="200" class="img-thumbnail">';
                    }
                } else {
                    targetInput.value = originalValue;
                    alert(data.message || '上传失败');
                }
            })
            .catch(error => {
                targetInput.disabled = false;
                targetInput.value = originalValue;
                console.error('上传错误:', error);
                alert('上传失败，请重试');
            });
        };

        document.body.appendChild(input);
        input.click();
        document.body.removeChild(input);
    };

    // 选择扩展字段图片并裁剪的函数
    window.selectExtendImageWithCrop = function(inputId, options) {
        selectImageWithCrop(inputId, options);
    };

    // 监听栏目变化
    if (categorySelect) {
        categorySelect.addEventListener('change', loadExtendFields);

        // 页面加载时，如果是编辑模式且有选中的栏目，或者添加模式通过 pre_cid 预选了栏目，自动加载扩展字段
        const shouldLoadExtendFields = (articleData && articleData.category_id) ||
                                      (categorySelect.selectedIndex > 0 && categorySelect.options[categorySelect.selectedIndex].value);

        if (shouldLoadExtendFields) {
            // 延迟执行，确保DOM已完全加载
            setTimeout(loadExtendFields, 100);
        }
    }

    // 为裁剪按钮添加事件监听器
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('crop-btn')) {
            e.preventDefault();
            const fieldName = e.target.getAttribute('data-field');
            const width = parseInt(e.target.getAttribute('data-width') || '0');
            const height = parseInt(e.target.getAttribute('data-height') || '0');
            const locked = e.target.getAttribute('data-locked') === 'true';
            
            // 调用图片裁剪函数
            const inputId = 'extend_' + fieldName;
            window.selectImageWithCrop(inputId, { width, height, locked });
        }
    });
})();
