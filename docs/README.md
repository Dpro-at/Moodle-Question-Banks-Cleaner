# استخراج الأسئلة والكويزات من Moodle

## الملفات المرفقة

1. **lib/question_extraction.php** - ملف توضيحي يحتوي على الدوال والأمثلة
2. **lib/get_questions_by_filter.php** - سكريبت جاهز للاستخدام
3. **docs/EXPLANATION_AR.md** - شرح مفصل بالعربية

## طريقة الاستخدام

### الطريقة 1: استخدام السكريبت المباشر

```bash
# من المتصفح
https://yoursite.com/local/questioncleaner/lib/get_questions_by_filter.php?courseid=48&filter={"category":{"name":"category","jointype":1,"values":[1372],"filteroptions":[{"name":"includesubcategories","value":true}]},"hidden":{"name":"hidden","jointype":1,"values":[0],"filteroptions":[]},"jointype":2}
```

### الطريقة 2: استخدام الدوال في الكود

```php
require_once($CFG->dirroot . '/local/questioncleaner/lib/question_extraction.php');

$filterstring = '{"category":{"values":[1372],"filteroptions":[{"name":"includesubcategories","value":true}]},"hidden":{"values":[0]},"jointype":2}';
$courseid = 48;

$filterdata = local_questioncleaner_decode_filter_parameter($filterstring);
$conditions = local_questioncleaner_extract_filter_conditions($filterdata);
$questions = local_questioncleaner_get_questions_by_filter($courseid, $conditions);
$quizzes = local_questioncleaner_get_quizzes_using_questions(array_keys($questions), $courseid);
```

## فهم Filter Parameter

### هيكل Filter JSON

```json
{
  "category": {
    "name": "category",
    "jointype": 1,
    "values": [1372],
    "filteroptions": [
      {
        "name": "includesubcategories",
        "value": true
      }
    ]
  },
  "hidden": {
    "name": "hidden",
    "jointype": 1,
    "values": [0],
    "filteroptions": []
  },
  "jointype": 2
}
```

### المعاملات:

- **category.values**: مصفوفة معرفات الفئات `[1372, 1371]`
- **category.filteroptions[].value**: `true` = تضمين الفئات الفرعية، `false` = فقط الفئة المحددة
- **hidden.values**: `[0]` = غير مخفية، `[1]` = مخفية
- **jointype**: `1` = AND، `2` = OR

## هيكل قاعدة البيانات

### الجداول الرئيسية:

```
question
  ├── question_versions (نسخ الأسئلة)
  │   └── question_bank_entries (تجمع النسخ)
  │       └── question_categories (الفئات)
  │
  └── question_references (ربط بالكويزات)
      └── quiz_slots
          └── quiz
```

### للأسئلة العشوائية:

```
question_set_references (شروط الفلترة)
  └── quiz_slots
      └── quiz
```

## أمثلة الاستعلامات

### 1. الحصول على الأسئلة من فئة محددة (الطريقة الصحيحة)

```sql
SELECT q.*, qc.name AS categoryname
FROM {question} q
JOIN {question_versions} qv ON q.id = qv.questionid
JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
WHERE qbe.questioncategoryid = 1372
  AND qv.status = 'ready'
  AND q.parent = 0
  AND qv.version = (SELECT MAX(v.version) FROM {question_versions} v 
                    WHERE v.questionbankentryid = qbe.id AND v.status <> 'hidden')
```

### 2. الحصول على الكويزات التي تستخدم سؤال معين

```sql
SELECT qz.id, qz.name, qs.slot
FROM {quiz} qz
JOIN {quiz_slots} qs ON qs.quizid = qz.id
JOIN {question_references} qr ON qr.itemid = qs.id
JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
WHERE qv.questionid = 123
  AND qr.component = 'mod_quiz'
  AND qr.questionarea = 'slot'
```

### 3. الحصول على الأسئلة العشوائية

```sql
SELECT qsr.filtercondition, qz.name, qs.slot
FROM {question_set_references} qsr
JOIN {quiz_slots} qs ON qs.id = qsr.itemid
JOIN {quiz} qz ON qz.id = qs.quizid
WHERE qsr.component = 'mod_quiz'
  AND qsr.questionarea = 'slot'
```

## ملاحظات مهمة

1. **العلاقة الصحيحة**: يجب استخدام `question_versions` كجسر بين `question` و `question_bank_entries`
2. **حالة الأسئلة**: يتم استخراج فقط الأسئلة بحالة "Ready" (ما لم يتم تحديد hidden=1)
3. **النسخ**: يتم استخدام أحدث نسخة غير مخفية
4. **الفئات الفرعية**: إذا كان `includesubcategories = true`، يتم تضمين جميع الفئات الفرعية بشكل متكرر
5. **السياق**: يتم فلترة الأسئلة بناءً على سياق الكورس

## الملفات المرجعية في Moodle

- `mod/quiz/classes/question/bank/custom_view.php` - بناء الاستعلام
- `mod/quiz/classes/question/bank/filter/custom_category_condition.php` - فلتر الفئات
- `mod/quiz/classes/question/bank/qbank_helper.php` - ربط الأسئلة بالكويزات
- `lib/questionlib.php` - دوال مساعدة
- `mod/quiz/locallib.php` - ربط الأسئلة بالكويزات

## الأمان

⚠️ **تحذير**: هذه الملفات للاستخدام الداخلي فقط. تأكد من:
- حماية الملفات من الوصول المباشر
- التحقق من الصلاحيات
- تنظيف المدخلات

## الدعم

للمزيد من المعلومات، راجع:
- `docs/EXPLANATION_AR.md` - شرح مفصل بالعربية
- `lib/question_extraction.php` - أمثلة الكود

