# URLTracker

请求参数获取及拼装实现类

## 首先 - 设置setter和builder

\* 绑定时可以传递更多参数

```php
URLTracker::setGetter({Function} callback [, {mixed} ...$args]);
URLTracker::setBuilder({Class method}array(\Class, 'Method') [, {mixed} ...args]);
```

### 举个laravel的例子

**自定义的builder**

```php
namespace App\Common;

use Illuminate\Support\Facades\Request;

class URLBuilder
{
    public static function build(array $query) {
        return Request::url() . ($query ? '?' . http_build_query($query) : '');
    }
}
```

**在Provider中注册getter和builder**

默认提供getter和builder

```php
class AppServiceProvider extends ServiceProvider
{
    public function boot(Request $request, URLBuilder $builder) {
        URLTracker::setGetter([$request, 'input']);
        URLTracker::setBuilder([$builder, 'build']);
    }

```

## 初始化 - 参数获取

根据当前请求获取参数，同时用来拼装模板

- **只能用在GET！只能用在GET！只能用在GET！**
- 虽然某些框架的参数获取是GET，POST混合的，但是强烈建议只用GET
- 字段名称尽量不要含有符号，最多只能有下划线"_"！

支持多维数组，变量限制只对第一维有效，深层次为递归继承（**array_replace_recursive**）

```php
URLTracker::init(array(
	'字段名1' => '默认值1',
	'字段名2' => '默认值2',
	...
));
```

### 例子

```php
// 发起一个页面请求: http://xxx.com/path?a=3&b=4

// ======== 初始化，同时接收参数 ========

$params = URLTracker::init(array(
	'a' => 1,
	'b' => 2,
));

print_r($params);
// ['a' => 3, 'b' => 4]

// *URLTracker是别的参数是强类型的，init中如果为int(1)，参数传入如果为string('A')，会被强制转为int
// 可以直接执行 extract(URLTracker::init(......)) 得到想要的变量


// ======== 在模板中使用 ========
// 效果是由builder决定的，默认的builder为 http_build_query

// == assign ==
// 设置一个值并返回query
// 默认 ['a' => 1, 'b' => 2]
// 当前 ['a' => 3, 'b' => 4]
URLTracker::assign('a', 1);
// 此时 ['a' => 1, 'b' => 4]， a 与默认值 1 相同，所以被过滤
// 得到 b=4
// *URLTracker会自动剔除掉与当前参数值相的项
// 继续执行
URLTracker::assign('b', 5);
// 此时 ['a' => 3, 'b' => 5] 为什么刚才设置了 a 的值却依然是 3？ assign不会改变原始的值，只有设置了第三个参数为 true 时，才会覆盖。
// 得到 a=3&b=5
URLTracker::assign('a', 2, true);
// 此时 ['a' => 2, 'b' => 2]
// 之后 a 就一直为 2 了

// == parse ==
// 批量设置多个值并返回query
// 同样，parse也支持第二个参数，bool是否覆盖当前值
URLTracker::parse('a=5&b=3&d=100');
// 或者
URLTracker::parse(['a' => 5, 'b' => 3, 'd' => 100]);
// 效果 a=5&b=3
// *不在init列表中的值不会添加

// == val ==
// 获取一个值
URLTracker::val('a');
// ==> 3

// == except ==
// 排除一个或多个key并返回query
URLTracker::except('a');
// 效果 b=2

// == del ==
// 将一个当前值恢复到默认值
URLTracker::del('a');
// 此时 ['a' => 1, 'b' => 2]

// assign 与 val 均支持多维数组，写法为 "a.b.c.d.e"
URLTracker::assign('a.b.c', 2000); 相当于设置 ['a'=>['b'=>['c'=>2000]]]
URLTracker::val('a.b.c'); ==> 2000

```