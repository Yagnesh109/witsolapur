from pathlib import Path
import re

branch_labels = {
    'cse': 'Computer Science & Engineering',
    'it': 'Information Technology',
    'me': 'Mechanical and Automation Engineering',
    'ce': 'Civil Engineering',
    'entc': 'Electronics & Telecommunication',
    'ecs': 'Electronics & Computer Engineering'
}

for page_name, label in branch_labels.items():
    path = Path('pages') / f'{page_name}.html'
    text = path.read_text(encoding='utf-8')
    original = text

    text = text.replace('<body class="dept-page">', f'<body class="dept-page" data-department="{page_name}">')
    text = text.replace('id="toggleSidebar"', 'id="menuToggle"')

    nav_item = f'        <li><button class="nav-link menu-trigger" type="button" data-menu="department">{label}</button></li>\n'
    if nav_item not in text:
        text = text.replace(
            '        <li><a class="nav-link" href="../index.html">Home</a></li>\n',
            '        <li><a class="nav-link" href="../index.html">Home</a></li>\n' + nav_item,
            1
        )

    text = re.sub(r'\n\s*<!-- Sidebar(?:.*?)-->\n\s*<aside class="sidebar" id="sidebar">[\s\S]*?</aside>\n\n', '\n', text, count=1)
    text = text.replace('  <div class="sidebar-overlay" id="sidebarOverlay"></div>\n\n', '')
    text = re.sub(r'\n\s*/\* ── Sidebar toggle ── \*/[\s\S]*?overlay\.addEventListener\(\'click\', closeSidebar\);\n', '\n', text, count=1)

    if text != original:
        path.write_text(text, encoding='utf-8')
        print(f'Updated {path}')
    else:
        print(f'No change for {path}')
