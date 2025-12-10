# عملية التنظيف - Cleanup Process

## نظرة عامة

هذا الملف يشرح بالتفصيل ما يتم حذفه في عملية التنظيف وكيفية التأكد من عدم حذف أي شيء مربوط في كورس فعلي.

## ما يتم حذفه

### 1. الأسئلة غير المستخدمة (Unused Questions)

#### ما يتم حذفه بالترتيب:
1. **`question_answers`** - جميع إجابات الأسئلة المحددة
2. **`qtype_*_options`** - خيارات أنواع الأسئلة (مثل `qtype_multichoice_options`, `qtype_shortanswer_options`)
3. **`question_versions`** - جميع نسخ الأسئلة
4. **`question_bank_entries`** - مدخلات Question Bank (فقط إذا لم تعد هناك نسخ)
5. **`question`** - الأسئلة نفسها

#### التحقق قبل الحذف:
- استخدام `verify_question_usage()` للتحقق من `question_references`
- التأكد من عدم وجود السؤال في `question_references` مع:
  - `component = 'mod_quiz'`
  - `questionarea = 'slot'`
- التحقق المزدوج: مرة قبل الحذف ومرة أثناء الحذف

### 2. الإجابات المعلقة (Orphaned Answers)

#### ما يتم حذفه:
- **`question_answers`** - الإجابات المعلقة فقط

#### الأمان:
- **آمنة 100% للحذف** لأنها مرتبطة بأسئلة محذوفة بالفعل
- التحقق: `qa.question` لا يوجد في جدول `question`
- لا تحتاج تحقق إضافي من `question_references`

### 3. إجابات الأسئلة غير المستخدمة (Answers for Unused Questions)

#### ما يتم حذفه:
- **`question_answers`** - إجابات الأسئلة غير المستخدمة فقط

#### التحقق قبل الحذف:
- التحقق من أن السؤال غير مستخدم عبر `verify_question_usage()`
- التأكد من عدم وجود السؤال في `question_references`
- فقط إجابات الأسئلة غير المستخدمة يتم حذفها

## التحققات الأمنية

### 1. التحقق من الاستخدام
```sql
-- التحقق من question_references
SELECT DISTINCT qr.questionbankentryid
FROM {question_references} qr
WHERE qr.component = 'mod_quiz'
  AND qr.questionarea = 'slot'
```

### 2. التحقق المزدوج
- قبل الحذف: التحقق من `question_references`
- أثناء الحذف: التحقق مرة أخرى قبل كل عملية حذف

### 3. التحقق من Orphaned Answers
```sql
-- التحقق من أن السؤال محذوف
SELECT qa.id
FROM {question_answers} qa
LEFT JOIN {question} q ON q.id = qa.question
WHERE q.id IS NULL
```

### 4. Batch Processing
- معالجة بالدفعات (1000-10000 سجل)
- إمكانية إيقاف العملية في أي وقت
- تسجيل جميع الأخطاء

## ما لا يتم حذفه

### الأسئلة المستخدمة:
- أي سؤال موجود في `question_references` مع `component='mod_quiz'`
- أي سؤال مرتبط بكويز نشط
- أي سؤال في `quiz_slots`

### الإجابات المرتبطة:
- إجابات الأسئلة المستخدمة
- إجابات الأسئلة المرتبطة بكويز

## أمثلة

### مثال 1: حذف سؤال غير مستخدم
```
1. التحقق: السؤال غير موجود في question_references ✓
2. حذف question_answers ✓
3. حذف qtype_multichoice_options ✓
4. حذف question_versions ✓
5. حذف question_bank_entries ✓
6. حذف question ✓
```

### مثال 2: حذف Orphaned Answers
```
1. التحقق: qa.question لا يوجد في question ✓
2. حذف question_answers ✓
```

### مثال 3: محاولة حذف سؤال مستخدم (سيتم منعه)
```
1. التحقق: السؤال موجود في question_references ✗
2. إيقاف العملية - لا يتم الحذف ✓
```

## الأمان

### الضمانات:
1. **التحقق المزدوج**: قبل وأثناء الحذف
2. **Batch Processing**: معالجة آمنة بالدفعات
3. **تسجيل الأخطاء**: جميع الأخطاء مسجلة
4. **تأكيدات**: تأكيدات قبل الحذف

### التوصيات:
- **Backup قاعدة البيانات** قبل أي عملية حذف
- **اختبار على بيئة تطوير** أولاً
- **مراقبة العملية** أثناء التنفيذ

## الملفات المرجعية

- `classes/cleaner.php` - دوال الحذف
- `mod/quiz/classes/question/bank/qbank_helper.php` - منطق الاستخدام
- `lib/db/install.xml` - هيكل قاعدة البيانات

