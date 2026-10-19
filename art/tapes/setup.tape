# First run after cloning, recorded in a throwaway copy of this checkout
# with no .env, vendor or node_modules, so it shows exactly what a new
# clone sees. Run from the project root: vhs art/tapes/setup.tape

Output art/setup.gif

Source art/tapes/settings.tape
Set Height 820

Hide
Type "export DEMO=$(mktemp -d) PS1='$ ' && unset JEV_FAKE JEV_API_KEY"
Enter
Type "rsync -a --exclude .git --exclude .env --exclude vendor --exclude node_modules --exclude .claude --exclude 'storage/logs/*' --exclude 'storage/framework/*/*' --exclude public/build --exclude public/hot ./ $DEMO/laravel-jev-hotdog && cd $DEMO/laravel-jev-hotdog && clear"
Enter
Sleep 2s
Show

Type "composer install --no-interaction --quiet"
Sleep 400ms
Enter
Wait@180s /\$\s*$/
Sleep 500ms

Type "npm install --silent && npm run build --silent -- --logLevel warn"
Sleep 400ms
Enter
Wait@180s /\$\s*$/
Sleep 500ms

Type "cp .env.example .env && php artisan key:generate"
Sleep 400ms
Enter
Sleep 2s

Type "grep JEV_ .env"
Sleep 400ms
Enter
Sleep 2s

Type "php artisan hotdog:check"
Sleep 400ms
Enter
Sleep 4s

Hide
Type "cd / && rm -rf $DEMO"
Enter
Sleep 2s
Show
