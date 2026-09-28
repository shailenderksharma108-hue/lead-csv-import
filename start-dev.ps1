Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PWD\laravel'; php artisan serve"

Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PWD\laravel'; php artisan queue:work"

Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PWD\react'; npm.cmd run dev"
