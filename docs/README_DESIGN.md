# تصميم إضافة Question Bank Cleaner

## نظرة عامة على التصميم

الإضافة مصممة لفحص وتنظيف Question Bank في Moodle باستخدام **الطريقة الصحيحة من Moodle الأساسي** لتحديد الأسئلة المرتبطة والمستخدمة.

## آلية العمل

### 1. الصفحة الرئيسية (index.php)

#### التصميم الحالي:
- **الفحص يبدأ تلقائياً** عند فتح الصفحة
- يتم تحميل الإحصائيات عبر **AJAX** (لا توجد شاشة بيضاء)
- يظهر **Progress Bar** يوضح:
  - العملية الحالية (مثال: "Loading total questions...")
  - النسبة المئوية للتقدم (0% → 100%)

#### خطوات الفحص:
1. **Loading total questions** (10%) - فحص إجمالي الأسئلة
2. **Checking duplicated questions** (30%) - فحص الأسئلة المكررة
3. **Checking unused questions** (50%) - فحص الأسئلة غير المستخدمة
4. **Checking orphaned answers** (70%) - فحص الإجابات المعلقة
5. **Checking unused question answers** (90%) - فحص إجابات الأسئلة غير المستخدمة
6. **Completing** (100%) - إكمال التحميل

#### الملفات المستخدمة:
- `index.php` - الصفحة الرئيسية (تعرض Progress Bar أولاً)
- `ajax/get_statistics.php` - نقطة AJAX لتحميل الإحصائيات

### 2. صفحات الفحص التفصيلية

#### duplicate_questions.php
- **الفحص يبدأ عند الضغط على "Load"**
- يعرض الأسئلة المكررة مجمعة حسب المفتاح

#### unused_questions.php
- **الفحص يبدأ عند الضغط على "Load"**
- يمكن فلترة حسب الكورس
- يعرض الأسئلة غير المستخدمة مع إمكانية التحقق

#### unused_answers.php
- **الفحص يبدأ عند الضغط على "Load"**
- يعرض نوعين:
  - Orphaned Answers (إجابات معلقة)
  - Answers for Unused Questions (إجابات الأسئلة غير المستخدمة)

### 3. صفحة التنظيف (cleanup.php)

- **لا توجد فحص تلقائي**
- تعرض معلومات وتحذيرات
- روابط سريعة للصفحات الأخرى

## الطريقة الصحيحة لتحديد الأسئلة

### العلاقة الصحيحة بين الجداول:
```
question → question_versions → question_bank_entries → question_categories → context
                ↓
        question_references → quiz_slots → quiz
```

### تحديد الأسئلة المرتبطة بالكورس:
- استخدام `context` و `question_categories`
- فلترة حسب: `ctx.id = coursecontextid OR ctx.path LIKE coursecontextpath`

### تحديد الأسئلة المستخدمة:
- استخدام `question_references` مع:
  - `component = 'mod_quiz'`
  - `questionarea = 'slot'`

### تحديد الأسئلة غير المستخدمة:
```sql
SELECT q.id, q.name, q.qtype, qbe.id AS questionbankentryid
FROM {question} q
JOIN {question_versions} qv ON qv.questionid = q.id
JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
LEFT JOIN {question_references} qr ON qr.questionbankentryid = qbe.id
  AND qr.component = 'mod_quiz'
  AND qr.questionarea = 'slot'
WHERE qr.id IS NULL
  AND q.parent = 0
  AND qv.version = (latest version)
  AND qv.status = 'ready'
```

## الأمان

### التحقق المزدوج:
1. قبل الحذف: يتم التحقق من الاستخدام عبر `verify_question_usage()`
2. أثناء الحذف: يتم التحقق مرة أخرى

### Batch Processing:
- معالجة بالدفعات (1000-10000 سجل)
- قابل للتخصيص من الإعدادات

## الأداء

### تحسينات الأداء:
- استخدام `delay()` بين الاستعلامات الثقيلة (0.1 ثانية)
- Batch processing للعمليات الكبيرة
- AJAX للتحميل غير المتزامن
- Progress Bar لإظهار التقدم

### للقواعد البيانات الكبيرة:
- زيادة timeout في AJAX (5 دقائق)
- زيادة memory limit (512MB)
- معالجة بالدفعات

## ملخص التصميم

| الصفحة | الفحص التلقائي | Progress Bar | AJAX |
|--------|----------------|--------------|------|
| index.php | ✅ نعم | ✅ نعم | ✅ نعم |
| duplicate_questions.php | ❌ لا (عند Load) | ❌ لا | ❌ لا |
| unused_questions.php | ❌ لا (عند Load) | ❌ لا | ❌ لا |
| unused_answers.php | ❌ لا (عند Load) | ❌ لا | ❌ لا |
| cleanup.php | ❌ لا | ❌ لا | ❌ لا |

## الملفات الرئيسية

### الكلاسات:
- `classes/cleaner.php` - الكلاس الرئيسي للفحص والتنظيف
  - `get_statistics()` - الحصول على الإحصائيات
  - `check_unused_questions()` - فحص الأسئلة غير المستخدمة
  - `verify_question_usage()` - التحقق من الاستخدام

### AJAX:
- `ajax/get_statistics.php` - نقطة AJAX لتحميل الإحصائيات

### الصفحات:
- `index.php` - الصفحة الرئيسية مع Progress Bar
- `duplicate_questions.php` - الأسئلة المكررة
- `unused_questions.php` - الأسئلة غير المستخدمة
- `unused_answers.php` - الإجابات غير المستخدمة
- `cleanup.php` - صفحة التنظيف

