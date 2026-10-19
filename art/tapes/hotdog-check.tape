# php artisan hotdog:check, recorded in fake mode with no key.
# Run from the project root: vhs art/tapes/hotdog-check.tape

Output art/hotdog-check.gif

Source art/tapes/settings.tape
Set Height 360

# Environment variables beat .env, so a real key in .env never reaches the recording.
Hide
Type "export JEV_FAKE=true JEV_API_KEY= PS1='$ ' && clear"
Enter
Sleep 500ms
Show

Type "php artisan hotdog:check"
Sleep 600ms
Enter
Sleep 3s
