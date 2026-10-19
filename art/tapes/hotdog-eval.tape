# php artisan hotdog:eval against the bundled samples, in fake mode.
# Run from the project root: vhs art/tapes/hotdog-eval.tape

Output art/hotdog-eval.gif

Source art/tapes/settings.tape
Set Width 1500
Set Height 760

Hide
Type "export JEV_FAKE=true JEV_API_KEY= PS1='$ ' && clear"
Enter
Sleep 500ms
Show

Type "php artisan hotdog:eval"
Sleep 600ms
Enter
Sleep 5s
