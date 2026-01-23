当前就是根目录，你不要加 \\wsl$\Ubuntu\var\www\yycms.com或者\var\www\yycms.com 你当前已经在根目录了

git status  # 查看哪些文件被修改/新增/删除

git add .          # 把所有修改的文件加入暂存区

git log --oneline  # 简洁查看提交记录（你之前用过的命令）

git commit -m "提交说明"  # 提交暂存区的修改，必须写清晰的说明



git pull origin <分支名>  # 拉取远程分支并合并到本地


git push origin <分支名>  # 推送本地分支到远程


git config --global user.email "koic@zhzz.org"
git config --global user.name "GuaiguaiYang"



