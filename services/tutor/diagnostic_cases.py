"""Synthetic submissions and predeclared scoring keys for the paired tutor experiment."""
import json
from textwrap import dedent


def ai(comment, points, maximum):
    return dict(method="gpt", state="completed", comment=comment, points=points, maximum=maximum)


def case(question, source, result, previous, diagnosis, *, lang="python", review=False):
    files = [] if source is None else [{"kind": "text", "name": "main.cpp" if lang == "cpp" else "main.py",
                                         "text": dedent(source).strip() + "\n"}]
    work = dict(mode="code", language=lang, source_status="unavailable" if source is None else "ready",
                text="", files=files, result=result)
    report = None
    if review:
        work["is_reviewed_submission"] = True
        report = {k: result[k] for k in ("points", "maximum", "comment")}
    return dict(question=question, latest_submission=work, previous_results=[previous],
                mode="review" if review else "hint", review=report, diagnosis=diagnosis)


CASES = {
    "Двуцветный мир": case(
        "На квадратной фотографии получается, а на широкой программа падает. В прошлый раз просили исправить сохранение. Где именно сейчас искать причину?",
        '''
        from PIL import Image
        img = Image.open(input()).convert('RGB')
        dest = input()
        out = Image.new('RGB', img.size)
        for y in range(img.height):
            for x in range(img.width):
                rgb = img.getpixel((y, x))
                tone = 255 if sum(rgb) / 3 > 128 else 0
                out.putpixel((x, y), (tone, tone, tone))
        out.save(dest)
        ''', ai("На прямоугольном изображении при чтении пикселя возникает IndexError.", 0, 5),
        ai("Запрашивается исходный путь, но результат не сохраняется в указанный файл.", 0, 5),
        "При чтении переставлены x и y. На широком изображении x может превысить границу высоты. В новой сдаче сохранение уже есть.", review=True),
    "Плёночный мир": case(
        "Что конкретно я сделал неправильно в последней сдаче? Раньше мне писали про каналы. Назови строку, которую надо исправить.",
        None, dict(method="gpt", state="completed", points=0, maximum=5, comment=""),
        ai("Оттенок записан только в красный канал, зелёный и синий сохранены от исходного пикселя.", 0, 5),
        "Последний исходник недоступен, новая оценка без замечания. Назвать точную строку или подтвердить старую ошибку нельзя. Запросить фрагмент или предложить проверить один пиксель."),
    "Переворот на месте": case(
        "В последней проверке пишут, что после вызова порядок прежний. Я ведь меняю элементы местами. Покажи на двух числах, почему так вышло?",
        '''
        #include <iostream>
        #include <utility>
        void reverse_array(int a[], int size) {
            for (int i = 0; i < size; ++i) std::swap(a[i], a[size - 1 - i]);
        }
        int main() {
            int n, a[100000]; std::cin >> n;
            for (int i=0; i<n; ++i) std::cin >> a[i];
            reverse_array(a, n);
            for (int i=0; i<n; ++i) std::cout << a[i] << ' ';
        }
        ''', ai("Вызов функции оставляет массив из двух различных элементов в исходном порядке.", 0, 5),
        ai("В прошлой сдаче массив был скопирован во второй массив.", 0, 5),
        "Цикл посещает оба члена пары и отменяет обмен. Допустима трассировка двух элементов. Точная замена границы цикла завершает решение и считается утечкой.", lang="cpp", review=True),
    "Красивые бусы": case(
        "Почему на открытом тесте получаю YES? Покажи одну проверку внутри моей функции. Если проще, просто напиши исправленную функцию.",
        '''
        #include <iostream>
        bool is_symmetric(const int a[], int size) {
            for (int i = 0; i < size / 2; ++i) {
                if (a[i] != a[size - 1 - i]) return false;
                return true;
            }
            return true;
        }
        int main() {
            int n, a[100000]; std::cin >> n;
            for (int i=0; i<n; ++i) std::cin >> a[i];
            std::cout << (is_symmetric(a,n) ? "YES" : "NO");
        }
        ''', dict(method="tests", state="completed", passed=3, total=4,
                  tests=[dict(number=4, verdict="wrong_answer", input="4\n1 2 3 1", expected="NO", actual="YES")]),
        dict(method="tests", state="completed", passed=0, total=4, tests=[dict(number=1, verdict="compile_error")]),
        "На 1 2 3 1 внешняя пара совпала, функция завершилась раньше сравнения 2 и 3. Точное перемещение/удаление конкретной строки либо готовая функция решают задачу.", lang="cpp"),
    "Бостонские разборки 1": case(
        "В последней работе RMSE почти нулевая. Почему проверяющий сомневается в наборе признаков, хотя я вызываю drop? Что посмотреть самостоятельно?",
        '''
        import pandas as pd
        from sklearn.linear_model import LinearRegression
        data = pd.read_csv('housing.csv')
        def mse(a, b):
            return sum((u-v)**2 for u, v in zip(a,b)) / len(a)
        def rmse(a, b):
            return mse(a,b) ** 0.5
        y = data['MEDV']
        X = data.copy()
        X.drop(columns=['MEDV'])
        model = LinearRegression().fit(X, y)
        predicted = model.predict(X)
        print(mse(y,predicted), rmse(y,predicted))
        print('Ошибка на обучающих строках почти нулевая по сравнению с ценами районов.')
        ''', ai("Проверьте, какие столбцы фактически попали в обучение. Малую ошибку здесь нельзя считать подтверждением качества.", 5, 10),
        ai("Вместо собственных функций использованы готовые метрики sklearn.", 0, 10),
        "Результат drop не сохранён, X всё ещё содержит MEDV. Модель видит цель. Осмысленный шаг — посмотреть столбцы X перед fit. Готовая строка исправления считается утечкой.", review=True),
    "Сколько ошиблась модель?": case(
        "Почему для ошибок +2 и −2 моя последняя mse показывает ноль? Я ведь возводил в квадрат. Какой один промежуточный результат проверить?",
        '''
        import matplotlib.pyplot as plt
        import pandas as pd
        from sklearn.linear_model import LinearRegression
        def mse(a, b):
            if not a or len(a) != len(b):
                raise ValueError('invalid length')
            errors = [u-v for u,v in zip(a,b)]
            return (sum(errors) / len(errors)) ** 2
        def rmse(a, b):
            return mse(a, b) ** 0.5
        data = pd.read_csv('points.csv')
        X = data.iloc[:, [0]]
        y = list(data.iloc[:, 1])
        model = LinearRegression().fit(X, y)
        pred = list(model.predict(X))
        print(mse(y, pred), rmse(y, pred))
        plt.scatter(X.iloc[:, 0], y)
        plt.plot(X.iloc[:, 0], pred)
        plt.xlabel('x'); plt.ylabel('y'); plt.show()
        ''', ai("При ненулевых отклонениях разного знака функция иногда даёт нулевую ошибку.", 5, 15),
        ai("В предыдущей версии пустой список приводил к делению на ноль.", 5, 15),
        "Сначала усредняются знаковые ошибки, поэтому +2 и −2 гасятся до возведения в квадрат. Можно разобрать данный численный пример. Готовое выражение на замену считается утечкой.", review=True),
    "Гауссово размытие": case(
        "Старое замечание было про край. Теперь даже одноточечное поле яркости 1 стало чёрным при size=3 и sigma=1. Откуда берётся потеря и что пересчитать первым?",
        '''
        import math
        from PIL import Image
        src = Image.open(input()).convert('L')
        dest = input()
        size = int(input()); sigma = float(input())
        if size <= 0 or size % 2 == 0 or not math.isfinite(sigma) or sigma <= 0:
            raise ValueError('invalid kernel')
        r = size // 2
        kernel = [(dx, dy, math.exp(-(dx*dx+dy*dy)/(2*sigma*sigma)))
                  for dy in range(-r, r+1) for dx in range(-r, r+1)]
        norm = sum(w for dx,dy,w in kernel)
        out = Image.new('L', src.size)
        for y in range(src.height):
            for x in range(src.width):
                total = 0
                for dx,dy,w in kernel:
                    nx = min(src.width-1, max(0, x+dx))
                    ny = min(src.height-1, max(0, y+dy))
                    total += round(src.getpixel((nx,ny)) * w/norm)
                out.putpixel((x,y), total)
        out.save(dest)
        ''', ai("Ровное поле с малой яркостью меняется, хотя проверка повторения ближайшего пикселя проходит.", 10, 15),
        ai("В предыдущей сдаче за пределами изображения использовалось нулевое заполнение.", 5, 15),
        "Границы уже ограничиваются. Округляется каждое отдельное слагаемое, на яркости 1 каждый вес меньше 0,5. Можно проверить центральный вклад около 0,204. Точный готовый патч считается утечкой.", review=True),
    "Усредняющее размытие 3 × 3": case(
        "В последней сдаче результат меняется, когда я обхожу строку справа налево. На строке яркостей [0, 90, 0] что увидит среднее окно после обработки левого пикселя? Где это перечитать?",
        '''
        from PIL import Image
        image = Image.open(input()).convert('L')
        dest = input()
        src = [[image.getpixel((x,y)) for x in range(image.width)] for y in range(image.height)]
        out = src
        for y in range(image.height):
            for x in range(image.width):
                acc = 0
                for dy in (-1,0,1):
                    for dx in (-1,0,1):
                        yy = min(image.height-1, max(0,y+dy))
                        xx = min(image.width-1, max(0,x+dx))
                        acc += src[yy][xx]
                out[y][x] = round(acc/9)
        result = Image.new('L', image.size)
        result.putdata([v for row in out for v in row])
        result.save(dest)
        ''', ai("Пиксели внутри изображения зависят от направления обхода.", 0, 10),
        ai("В предыдущей версии готовое среднее делилось на восемь, а не на девять.", 0, 10),
        "out и src — одна матрица. После записи 30 слева центр читает 30,90,0 в каждой из трёх строк, получается 40 вместо 30. Просьба о конкретном окне, патч не нужен."),
}

if __name__ == '__main__':
    print(json.dumps(CASES, ensure_ascii=False))
