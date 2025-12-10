# شرح طريقة استخراج الأسئلة والكويزات من Moodle

## نظرة عامة

هذا الملف يشرح كيفية استخراج الأسئلة والكويزات المرتبطة بالكورس بناءً على filter parameter المستخدم في Question Bank.

## الروابط المثال

```
https://deutsch-digital-testing.dieberater.com/question/edit.php?courseid=48&filter={"category":{"name":"category","jointype":1,"values":[1372],"filteroptions":[{"name":"includesubcategories","value":true}]},"hidden":{"name":"hidden","jointype":1,"values":[0],"filteroptions":[]},"jointype":2}
```

## خطوات العملية

### 1. فك تشفير Filter Parameter

الـ filter يأتي كـ JSON مُرمّز في URL:

```php
$filterstring = '{"category":{"name":"category","jointype":1,"values":[1372],...}';
$decoded = json_decode(urldecode($filterstring), true);
```

### 2. استخراج شروط الفلترة

من الـ filter المُفكك، نستخرج:

- **Category Filter**: معرفات الفئات المطلوبة
  - `values`: [1372] - معرفات الفئات
  - `includesubcategories`: true/false - هل نضمّن الفئات الفرعية

- **Hidden Filter**: فلتر الأسئلة المخفية
  - `values`: [0] = غير مخفية، [1] = مخفية

- **Jointype**: نوع الربط بين الشروط
  - `1` = AND (جميع الشروط)
  - `2` = OR (أي شرط)

### 3. بناء استعلام SQL (الطريقة الصحيحة من Moodle)

يتم بناء استعلام SQL مشابه لما في `custom_view.php`:

```sql
SELECT DISTINCT q.id, q.name, q.questiontext, ...
FROM {question} q
JOIN {question_versions} qv ON q.id = qv.questionid
JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
JOIN {context} ctx ON ctx.id = qc.contextid
WHERE (ctx.id = :coursecontextid OR ctx.path LIKE :coursecontextpath)
  AND q.parent = 0
  AND qv.version = (latest version)
  AND qv.status = 'ready'
  AND qbe.questioncategoryid IN (category_ids)
```

### 4. ربط الأسئلة بالكويزات

#### أ) الأسئلة المباشرة (question_references)

```sql
SELECT qz.id, qz.name, qs.slot, q.id AS questionid
FROM {quiz} qz
JOIN {quiz_slots} qs ON qs.quizid = qz.id
JOIN {question_references} qr ON qr.itemid = qs.id
JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
JOIN {question} q ON q.id = qv.questionid
WHERE q.id IN (question_ids)
  AND qr.component = 'mod_quiz'
  AND qr.questionarea = 'slot'
```

#### ب) الأسئلة العشوائية (question_set_references)

```sql
SELECT qsr.filtercondition, qs.quizid, qz.name
FROM {question_set_references} qsr
JOIN {quiz_slots} qs ON qs.id = qsr.itemid
JOIN {quiz} qz ON qz.id = qs.quizid
WHERE qsr.component = 'mod_quiz'
  AND qsr.questionarea = 'slot'
```

## هيكل قاعدة البيانات

### الجداول الرئيسية:

1. **question**: بيانات الأسئلة
2. **question_bank_entries**: تجميع نسخ الأسئلة
3. **question_versions**: نسخ مختلفة من نفس السؤال
4. **question_categories**: فئات الأسئلة
5. **question_references**: ربط أسئلة محددة بمواضع الكويز
6. **question_set_references**: ربط شروط الفلترة بمواضع الأسئلة العشوائية
7. **quiz_slots**: مواضع الأسئلة في الكويز
8. **quiz**: بيانات الكويزات

### العلاقات الصحيحة:

```
question → question_versions → question_bank_entries → question_categories → context
                ↓
        question_references → quiz_slots → quiz
                ↓
        question_set_references → quiz_slots → quiz (للأسئلة العشوائية)
```

## مثال عملي

```php
// 1. فك تشفير الـ filter
$filterdata = local_questioncleaner_decode_filter_parameter($filterstring);

// 2. استخراج الشروط
$categoryids = $filterdata['category']['values']; // [1372]
$includesub = $filterdata['category']['filteroptions'][0]['value']; // true

// 3. الحصول على الأسئلة (الطريقة الصحيحة)
$questions = local_questioncleaner_get_questions_by_filter($courseid, $conditions);

// 4. الحصول على الكويزات التي تستخدم هذه الأسئلة
$quizzes = local_questioncleaner_get_quizzes_using_questions(array_keys($questions), $courseid);
```

## ملاحظات مهمة

1. **العلاقة الصحيحة**: `question.id` → `question_versions.questionid` → `question_versions.questionbankentryid` → `question_bank_entries.id`
2. **حالة الأسئلة**: يتم استخراج فقط الأسئلة بحالة "Ready" (جاهزة)
3. **النسخ**: يتم استخدام أحدث نسخة غير مخفية من كل سؤال
4. **الفئات الفرعية**: إذا كان `includesubcategories = true`، يتم تضمين جميع الفئات الفرعية
5. **الأسئلة العشوائية**: يتم تخزين شروط الفلترة في `question_set_references.filtercondition` كـ JSON

## الملفات المرجعية في الكود

- `mod/quiz/classes/question/bank/custom_view.php` - بناء الاستعلام
- `mod/quiz/classes/question/bank/filter/custom_category_condition.php` - فلتر الفئات
- `mod/quiz/classes/question/bank/qbank_helper.php` - ربط الأسئلة بالكويزات
- `lib/questionlib.php` - دوال مساعدة للأسئلة
- `mod/quiz/locallib.php` - ربط الأسئلة بالكويزات

