/**
 * Coral 自定义脚本
 * - 代码块 Copy 按钮
 * - 代码块超过5行自动折叠（磨砂玻璃 + 底部中央按钮）
 * - Task List 复选框样式
 */

/* ---- 工具函数：复制到剪贴板 ---- */
function copyHandle(content, copyDiv) {
    if (!navigator.clipboard) {
        var textarea = document.createElement("textarea");
        textarea.value = content;
        textarea.style.position = "fixed";
        textarea.style.opacity = "0";
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            document.execCommand("copy");
            showCopied(copyDiv);
        } catch (err) {
            alert("复制失败，请手动复制。");
        }
        document.body.removeChild(textarea);
        return;
    }
    navigator.clipboard.writeText(content).then(function () {
        showCopied(copyDiv);
    }, function (err) {
        console.error('复制失败: ', err);
        alert("复制失败，请手动复制。");
    });
}

function showCopied(copyDiv) {
    var $copyDiv = $(copyDiv);
    var copyText = $copyDiv.find('.copyText');
    copyText.text('Copied!');
    $copyDiv.addClass('copied');
    setTimeout(function () {
        copyText.text('Copy');
        $copyDiv.removeClass('copied');
    }, 2000);
}

/* ---- 初始化代码块 Copy 按钮 ---- */
function initializeCopyButtons() {
    $('.articleBody pre').each(function () {
        if ($(this).siblings('.copyDiv').length === 0) {
            var copyButton = '<div class="copyDiv">' +
                '<svg aria-hidden="true" role="img" class="clipboard-icon" viewBox="0 0 16 16" fill="currentColor">' +
                '<path fill-rule="evenodd" d="M5.75 1a.75.75 0 00-.75.75v3c0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75v-3a.75.75 0 00-.75-.75h-4.5zm.75 3V2.5h3V4h-3zm-2.874-.467a.75.75 0 00-.752-1.298A1.75 1.75 0 002 3.75v9.5c0 .966.784 1.75 1.75 1.75h8.5A1.75 1.75 0 0014 13.25v-9.5a1.75 1.75 0 00-.874-1.515.75.75 0 10-.752 1.298.25.25 0 01.126.217v9.5a.25.25 0 01-.25.25h-8.5a.25.25 0 01-.25-.25v-9.5a.25.25 0 01.126-.217z"></path>' +
                '</svg>' +
                '<span class="copyText user-select-none">Copy</span>' +
                '</div>';
            $(this).append(copyButton);
        }
    });
}

$(document).on('click', '.copyDiv', function () {
    var $pre = $(this).parent();
    var codeContent = $pre.find('code').text().trim();
    copyHandle(codeContent, this);
});

/* ---- 代码块折叠：超过5行自动折叠，按钮始终在底部中央 ---- */
function initializeCodeFold() {
    $('.articleBody pre').each(function () {
        var $pre = $(this);
        // 跳过已处理的
        if ($pre.parent('.code-collapse-wrapper').length > 0) return;

        var lines = $pre.text().split('\n');
        // 不超过5行不折叠
        if (lines.length <= 5) return;

        // 构建结构: .code-collapse-wrapper > pre + .code-fold-overlay + .code-fold-toggle
        var $wrapper = $('<div class="code-collapse-wrapper collapsed"></div>');
        $pre.wrap($wrapper);
        var $wrap = $pre.parent('.code-collapse-wrapper');

        // 磨砂玻璃遮罩层
        var $overlay = $('<div class="code-fold-overlay"></div>');
        $wrap.append($overlay);

        // 切换按钮 — 始终在底部中央
        var $btn = $('<button class="code-fold-toggle">展开 ' + lines.length + ' 行</button>');
        $wrap.append($btn);

        $btn.on('click', function () {
            var $w = $(this).parent('.code-collapse-wrapper');
            if ($w.hasClass('collapsed')) {
                $w.removeClass('collapsed');
                $(this).text('收起');
            } else {
                $w.addClass('collapsed');
                $(this).text('展开 ' + lines.length + ' 行');
            }
        });
    });
}

/* ---- Task List 复选框 ---- */
function styleTaskList() {
    $("ul li").each(function (index, ele) {
        var cur_str = $(this).text();
        if (cur_str.startsWith('[x]') || cur_str.startsWith('[X]')) {
            $(this).addClass('task-list-item').empty()
                .prepend('<input type="checkbox" checked>')
                .append('<span class="task-list-done">' + cur_str.slice(3).trim() + '</span>');
        } else if (cur_str.startsWith('[ ]')) {
            $(this).addClass('task-list-item').empty()
                .prepend('<input type="checkbox">')
                .append('<span>' + cur_str.slice(3).trim() + '</span>');
        }
    });
}

/* ---- 运行所有初始化 ---- */
function runAllCustom() {
    initializeCopyButtons();
    styleTaskList();
    initializeCodeFold();
}

/* ---- 页面加载完成后初始化 ---- */
$(document).ready(function () {
    runAllCustom();
});

/* ---- PJAX 切换后重新初始化 ---- */
$(document).on('pjax:complete', function () {
    runAllCustom();
});
