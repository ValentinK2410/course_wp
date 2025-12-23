# Настройка синхронизации курсов с Laravel приложением

## Описание

При синхронизации курсов из Moodle в WordPress, курсы теперь автоматически отправляются в Laravel приложение (m.dekan.pro) через API.

## Что было добавлено в WordPress плагин

В файле `course-plugin/includes/class-course-moodle-sync.php` добавлен метод `sync_course_to_laravel()`, который вызывается после успешного сохранения курса в WordPress.

## Что нужно сделать в Laravel приложении

### 1. Создать API Controller для синхронизации курсов

Создайте файл `app/Http/Controllers/Api/CourseSyncController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseSyncController extends Controller
{
    /**
     * Синхронизация курса из WordPress
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncFromWordPress(Request $request)
    {
        // Проверяем API токен
        $token = $request->header('X-API-Token');
        $expectedToken = config('services.wordpress.api_token');
        
        if (!$token || $token !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        // Валидация данных
        $validator = Validator::make($request->all(), [
            'wordpress_course_id' => 'required|integer',
            'moodle_course_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'duration' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:0',
            'enrolled' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:publish,draft,pending,private',
            'action' => 'required|string|in:created,updated',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $validator->validated();
        
        // Ищем существующий курс по WordPress ID или Moodle ID
        $course = Course::where('wordpress_course_id', $data['wordpress_course_id'])
            ->orWhere('moodle_course_id', $data['moodle_course_id'])
            ->first();
        
        if ($course) {
            // Обновляем существующий курс
            $course->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'moodle_course_id' => $data['moodle_course_id'] ?? $course->moodle_course_id,
                'category_id' => $data['category_id'] ?? null,
                'category_name' => $data['category_name'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'duration' => $data['duration'] ?? null,
                'price' => $data['price'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'enrolled' => $data['enrolled'] ?? 0,
                'status' => $data['status'] ?? 'active',
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully',
                'course' => $course
            ], 200);
        } else {
            // Создаем новый курс
            $course = Course::create([
                'wordpress_course_id' => $data['wordpress_course_id'],
                'moodle_course_id' => $data['moodle_course_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'short_description' => $data['short_description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'category_name' => $data['category_name'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'duration' => $data['duration'] ?? null,
                'price' => $data['price'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'enrolled' => $data['enrolled'] ?? 0,
                'status' => $data['status'] ?? 'active',
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Course created successfully',
                'course' => $course
            ], 201);
        }
    }
}
```

### 2. Добавить маршрут в `routes/api.php`

```php
use App\Http\Controllers\Api\CourseSyncController;

Route::post('/courses/sync-from-wordpress', [CourseSyncController::class, 'syncFromWordPress']);
```

### 3. Обновить модель Course (если нужно)

Убедитесь, что модель `Course` имеет следующие поля:
- `wordpress_course_id` (integer, nullable)
- `moodle_course_id` (integer, nullable)
- `name` (string)
- `description` (text, nullable)
- `short_description` (string, nullable)
- `category_id` (integer, nullable)
- `category_name` (string, nullable)
- `start_date` (date, nullable)
- `end_date` (date, nullable)
- `duration` (integer, nullable)
- `price` (decimal, nullable)
- `capacity` (integer, nullable)
- `enrolled` (integer, default: 0)
- `status` (string, default: 'active')

### 4. Создать миграцию (если поля отсутствуют)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'wordpress_course_id')) {
                $table->unsignedBigInteger('wordpress_course_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('courses', 'moodle_course_id')) {
                $table->unsignedBigInteger('moodle_course_id')->nullable()->after('wordpress_course_id');
            }
            if (!Schema::hasColumn('courses', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable();
            }
            if (!Schema::hasColumn('courses', 'category_name')) {
                $table->string('category_name')->nullable();
            }
            if (!Schema::hasColumn('courses', 'short_description')) {
                $table->text('short_description')->nullable();
            }
            if (!Schema::hasColumn('courses', 'enrolled')) {
                $table->integer('enrolled')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'wordpress_course_id',
                'moodle_course_id',
                'category_id',
                'category_name',
                'short_description',
                'enrolled'
            ]);
        });
    }
};
```

### 5. Обновить `config/services.php`

Убедитесь, что в файле `config/services.php` есть настройка для WordPress API токена:

```php
'wordpress' => [
    'url' => env('WORDPRESS_URL', 'https://site.dekan.pro'),
    'api_token' => env('WORDPRESS_API_TOKEN'),
],
```

### 6. Обновить `.env` файл

Добавьте в `.env` файл:

```
WORDPRESS_API_TOKEN=your-secret-token-here
```

Этот токен должен совпадать с токеном, указанным в настройках WordPress плагина (`laravel_api_token`).

### 7. Исключить маршрут из CSRF защиты

В файле `bootstrap/app.php` или `app/Http/Middleware/VerifyCsrfToken.php` добавьте исключение:

```php
protected $except = [
    'api/courses/sync-from-wordpress',
];
```

## Тестирование

После настройки Laravel приложения:

1. Убедитесь, что в WordPress настроены:
   - Laravel API URL: `https://m.dekan.pro`
   - Laravel API Token: должен совпадать с `WORDPRESS_API_TOKEN` в Laravel

2. Выполните синхронизацию курсов из Moodle в WordPress через админ-панель WordPress

3. Проверьте логи Laravel приложения на наличие ошибок

4. Проверьте, что курсы создаются/обновляются в Laravel приложении

## Формат данных, отправляемых из WordPress

```json
{
    "wordpress_course_id": 123,
    "moodle_course_id": 45,
    "name": "Название курса",
    "description": "Полное описание курса",
    "short_description": "Краткое описание",
    "category_id": 10,
    "category_name": "Название категории",
    "start_date": "2025-01-15",
    "end_date": "2025-06-15",
    "duration": 120,
    "price": 15000.00,
    "capacity": 30,
    "enrolled": 5,
    "status": "publish",
    "action": "created"
}
```

## Логирование

Все операции синхронизации логируются в WordPress:
- Успешная синхронизация: `Moodle Sync: Курс успешно синхронизирован с Laravel приложением`
- Ошибки: `Moodle Sync: Ошибка синхронизации курса с Laravel`

Логи можно найти в:
- WordPress: `wp-content/debug.log` (если включен `WP_DEBUG`)
- WordPress: `wp-content/uploads/course-plugin-logs/sync-YYYY-MM-DD.log` (если используется Course_Logger)

