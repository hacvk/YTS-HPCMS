/**
 * 专家列表筛选功能
 */
(function() {
    'use strict';

    // 切换科室标签页
    function switchDeptTab(tab) {
        var tabs = document.querySelectorAll('.dept-tab');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        
        var activeTab = document.querySelector('.dept-tab[data-tab="' + tab + '"]');
        if (activeTab) {
            activeTab.classList.add('active');
        }
        
        var clinicalDepts = document.getElementById('clinical-depts');
        var medicalTechDepts = document.getElementById('medical_tech-depts');
        
        if (clinicalDepts) {
            clinicalDepts.style.display = tab === 'clinical' ? 'grid' : 'none';
        }
        if (medicalTechDepts) {
            medicalTechDepts.style.display = tab === 'medical_tech' ? 'grid' : 'none';
        }
    }

    // 应用筛选
    function applyFilters() {
        var deptCheckboxes = document.querySelectorAll('input[name="dept"]:checked');
        var titleCheckboxes = document.querySelectorAll('input[name="title"]:checked');
        var searchInput = document.getElementById('doctor-search');
        
        var selectedDepts = [];
        for (var i = 0; i < deptCheckboxes.length; i++) {
            selectedDepts.push(deptCheckboxes[i].value);
        }
        
        var selectedTitles = [];
        for (var j = 0; j < titleCheckboxes.length; j++) {
            selectedTitles.push(titleCheckboxes[j].value);
        }
        
        var searchKeyword = searchInput ? searchInput.value.trim() : '';
        
        var doctorItems = document.querySelectorAll('.Doctor_list li');
        var visibleCount = 0;
        
        for (var k = 0; k < doctorItems.length; k++) {
            var item = doctorItems[k];
            
            // 跳过提示信息项
            if (item.classList && item.classList.contains('no-result-msg')) {
                continue;
            }
            
            var visible = true;
            
            // 科室筛选
            if (selectedDepts.length > 0) {
                var deptLinks = item.querySelectorAll('aside a');
                var hasMatchingDept = false;
                if (deptLinks && deptLinks.length > 0) {
                    for (var m = 0; m < deptLinks.length; m++) {
                        var link = deptLinks[m];
                        var href = link.getAttribute('href') || '';
                        // 提取科室ID：/article/123 -> 123
                        var deptId = href.replace('/article/', '').split('/')[0].split('?')[0];
                        if (deptId && selectedDepts.indexOf(deptId) !== -1) {
                            hasMatchingDept = true;
                            break;
                        }
                    }
                }
                if (!hasMatchingDept) {
                    visible = false;
                }
            }
            
            // 职称筛选
            if (visible && selectedTitles.length > 0) {
                var titleSpan = item.querySelector('.txt h3 span');
                var titleText = titleSpan ? titleSpan.textContent.trim() : '';
                var hasMatchingTitle = false;
                for (var n = 0; n < selectedTitles.length; n++) {
                    if (titleText.indexOf(selectedTitles[n]) !== -1) {
                        hasMatchingTitle = true;
                        break;
                    }
                }
                if (!hasMatchingTitle) {
                    visible = false;
                }
            }
            
            // 关键词搜索
            if (visible && searchKeyword) {
                var titleLink = item.querySelector('.txt h3 a');
                var titleText = titleLink ? titleLink.textContent : '';
                var descElement = item.querySelector('.txt .word p');
                var descText = descElement ? descElement.textContent : '';
                var searchLower = searchKeyword.toLowerCase();
                if (titleText.toLowerCase().indexOf(searchLower) === -1 && 
                    descText.toLowerCase().indexOf(searchLower) === -1) {
                    visible = false;
                }
            }
            
            if (visible) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        }
        
        // 如果没有可见项，显示提示
        var listContainer = document.querySelector('.Doctor_list ul');
        if (listContainer) {
            var noResultMsg = listContainer.querySelector('.no-result-msg');
            if (visibleCount === 0) {
                if (!noResultMsg) {
                    noResultMsg = document.createElement('li');
                    noResultMsg.className = 'no-result-msg';
                    noResultMsg.style.cssText = 'padding:20px 0;text-align:center;width:100%;display:block;';
                    noResultMsg.textContent = '暂无符合条件的专家信息';
                    listContainer.appendChild(noResultMsg);
                }
            } else {
                if (noResultMsg) {
                    noResultMsg.remove();
                }
            }
        }
    }

    // 清空筛选
    function clearFilters() {
        var deptInputs = document.querySelectorAll('input[name="dept"]');
        var titleInputs = document.querySelectorAll('input[name="title"]');
        var searchInput = document.getElementById('doctor-search');
        
        for (var i = 0; i < deptInputs.length; i++) {
            deptInputs[i].checked = false;
        }
        for (var j = 0; j < titleInputs.length; j++) {
            titleInputs[j].checked = false;
        }
        if (searchInput) {
            searchInput.value = '';
        }
        
        // 切换回临床科室标签
        switchDeptTab('clinical');
        
        // 重置显示
        var doctorItems = document.querySelectorAll('.Doctor_list li');
        for (var k = 0; k < doctorItems.length; k++) {
            var item = doctorItems[k];
            if (!item.classList.contains('no-result-msg')) {
                item.style.display = '';
            }
        }
        
        var noResultMsg = document.querySelector('.no-result-msg');
        if (noResultMsg) {
            noResultMsg.remove();
        }
    }

    // 更新复选框的 checked 类状态
    function updateCheckboxClasses() {
        var deptInputs = document.querySelectorAll('input[name="dept"]');
        var titleInputs = document.querySelectorAll('input[name="title"]');
        
        for (var i = 0; i < deptInputs.length; i++) {
            var input = deptInputs[i];
            var label = input.closest('.dept-item');
            if (label) {
                if (input.checked) {
                    label.classList.add('checked');
                } else {
                    label.classList.remove('checked');
                }
            }
        }
        
        for (var j = 0; j < titleInputs.length; j++) {
            var input2 = titleInputs[j];
            var label2 = input2.closest('.title-item');
            if (label2) {
                if (input2.checked) {
                    label2.classList.add('checked');
                } else {
                    label2.classList.remove('checked');
                }
            }
        }
    }

    // 页面加载完成后初始化
    function init() {
        // 将函数绑定到全局作用域，供 HTML 中的 onclick 调用（兼容性）
        window.switchDeptTab = switchDeptTab;
        window.applyFilters = applyFilters;
        window.clearFilters = clearFilters;
        
        // 监听科室标签切换
        var deptTabs = document.querySelectorAll('.dept-tab');
        for (var i = 0; i < deptTabs.length; i++) {
            deptTabs[i].addEventListener('click', function() {
                var tab = this.getAttribute('data-tab');
                if (tab) {
                    switchDeptTab(tab);
                }
            });
        }
        
        // 监听清空筛选按钮
        var clearBtn = document.querySelector('.btn-clear-filter');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                clearFilters();
                updateCheckboxClasses();
            });
        }
        
        // 监听所有复选框的变化，更新 checked 类并应用筛选
        var allCheckboxes = document.querySelectorAll('input[name="dept"], input[name="title"]');
        for (var j = 0; j < allCheckboxes.length; j++) {
            allCheckboxes[j].addEventListener('change', function() {
                updateCheckboxClasses();
                applyFilters();
            });
        }
        
        // 监听搜索框回车事件
        var searchInput = document.getElementById('doctor-search');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.keyCode === 13) {
                    applyFilters();
                }
            });
        }
        
        // 监听搜索按钮点击事件
        var searchBtn = document.querySelector('.btn-search');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                applyFilters();
            });
        }
        
        // 初始化复选框状态
        updateCheckboxClasses();
    }

    // DOM 加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
