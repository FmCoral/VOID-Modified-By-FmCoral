针对 Typecho (v1.2.1) VOID (3.5.1) 主题，结合本人喜好所做的一些修改

[点击查看效果](https://blog.fmcoral.com)

## patches / 核心补丁

`patches/HyperDown.php` — 对 Typecho Markdown 解析器的修改：

- **GFM 任务列表支持**：`- [ ]` 渲染为复选框，`- [x]` 渲染为已勾选复选框
- 配合 VOID 主题的 `custom.css` 使用（`.task-list-item` / `.task-checkbox` 样式）
- 将此文件替换 `网站/var/Utils/HyperDown.php` 即可生效
