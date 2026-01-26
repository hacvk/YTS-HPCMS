/**
 * 专家列表筛选功能
 */
(function() {
    'use strict';

    // 切换科室标签页
    function switchDeptTab(tab) {
        if (!tab) {
            return;
        }
        var tabs = document.querySelectorAll('.dept-tab');
        for (var i = 0; i < tabs.length; i++) {
            var t = tabs[i];
            if (t.getAttribute('data-tab') === tab) {
                t.classList.add('active');
            } else {
                t.classList.remove('active');
            }
        }
        var groups = document.querySelectorAll('.dept-group');
        for (var j = 0; j < groups.length; j++) {
            var g = groups[j];
            if (g.id === tab) {
                g.style.display = '';
                g.classList.add('active');
            } else {
                g.style.display = 'none';
                g.classList.remove('active');
            }
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
        
        var params = [];
        if (selectedDepts.length > 0) {
            params.push('dept=' + selectedDepts.join(','));
        }
        if (selectedTitles.length > 0) {
            params.push('title=' + encodeURIComponent(selectedTitles.join(',')));
        }
        if (searchKeyword) {
            params.push('keyword=' + encodeURIComponent(searchKeyword));
        }
        var basePath = window.location.pathname.replace(/\/page\/\d+$/, '');
        var newUrl = params.length > 0 ? (basePath + '?' + params.join('&')) : basePath;
        window.location.href = newUrl;
    }

    // 清空筛选
    function clearFilters() {
        var basePath = window.location.pathname.replace(/\/page\/\d+$/, '');
        window.location.href = basePath;
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
        
        // 绑定所有事件监听器
        function bindEvents() {
            // 监听科室标签切换
            var deptTabs = document.querySelectorAll('.dept-tab');
            if (deptTabs.length > 0) {
                for (var i = 0; i < deptTabs.length; i++) {
                    (function(tabElement) {
                        // 移除可能存在的旧事件监听器（通过克隆节点）
                        var newTabElement = tabElement.cloneNode(true);
                        if (tabElement.parentNode) {
                            tabElement.parentNode.replaceChild(newTabElement, tabElement);
                        }
                        
                        newTabElement.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            var tab = newTabElement.getAttribute('data-tab');
                            if (tab) {
                                switchDeptTab(tab);
                            }
                        });
                        
                        // 确保按钮可点击
                        newTabElement.style.cursor = 'pointer';
                        newTabElement.style.pointerEvents = 'auto';
                    })(deptTabs[i]);
                }
            }
            
            // 监听清空筛选按钮
            var clearBtn = document.querySelector('.btn-clear-filter');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
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
                searchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    applyFilters();
                });
            }
            
            // 初始化复选框状态
            updateCheckboxClasses();
        }
        
        // 立即尝试绑定，如果失败则延迟重试
        bindEvents();
    }

    // DOM 加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
