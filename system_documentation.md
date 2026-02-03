# وثيقة متطلبات النظام (System Requirements Specification)

## 1. نظرة عامة (Project Overview)
المطلوب بناء "نظام إدارة وتوزيع المصممين" (Designer Distribution System). هو نظام برمجي يهدف إلى أتمتة عملية تعيين المصممين للعملاء وتوزيع مهام التصميم اليومية بناءً على خوارزميات ذكية تضمن الكفاءة والعدالة.

### المكدس التقني المطلوب (Required Tech Stack)
يجب بناء النظام باستخدام التقنيات التالية حصراً:
*   **Backend:** [NestJS](https://nestjs.com/) (Node.js Framework).
*   **Database:** PostgreSQL أو MySQL (مع استخدام **Prisma ORM**).
*   **Frontend:** React.js (أو إطار عمل مبني عليه مثل Next.js أو Refine).
*   **Language:** TypeScript (Full-stack).

---

## 2. هيكلية البيانات (Data Architecture)

### مخطط قاعدة البيانات (ER Diagram)
يوضح المخطط التالي الكيانات التي يجب إنشاؤها في قاعدة البيانات والعلاقات بينها.

```mermaid
erDiagram
    User ||--o{ Designer : "has profile"
    Category ||--o{ Client : "classifies"
    Category ||--o{ Tag : "contains default tags"
    
    Client ||--o{ Subscription : "has"
    Client ||--o{ ClientIdea : "has ideas"
    
    Subscription ||--o{ ClientDesigner : "assigned in"
    Subscription ||--o{ Tag : "has custom tags"
    
    Designer ||--o{ ClientDesigner : "assigned to"
    Designer ||--o{ Category : "specializes in"
    
    TagGroup ||--o{ Tag : "groups"
    Tag ||--o{ ClientTagDistribution : "distributed as"
    
    ClientDesigner ||--o{ ClientTagDistribution : "has tasks"

    Client {
        int id
        string company
        string client_name
        int category_id
    }

    Designer {
        int id
        int user_id
        float rate
        int max_capacity
        int amount_of_designs
    }

    Subscription {
        int id
        int client_id
        boolean is_main
        int designs_count
        date start_date
        date end_date
    }

    Tag {
        int id
        string title
        string importance "Very High, High, Medium, Low"
        boolean is_there_date_for_sending
        string weekly_day
        date date_for_sending_yearly
    }

    ClientDesigner {
        int id
        int client_id
        int designer_id
        int subscription_id
        date week_start_date
    }
```

### تعريف الكيانات (Entities Definition)
1.  **Client (العميل):** الجهة الطالبة للخدمة.
2.  **Designer (المصمم):** الموظف المنفذ. يجب تخزين سعته القصوى (`max_capacity`) وتقييم أدائه (`rate`).
3.  **Subscription (الاشتراك):** يحدد عدد التصاميم المطلوبة أسبوعياً.
    *   **نوعان:** "أساسي" (Main) و "ثانوي" (Secondary).
4.  **ClientDesigner (التعيين):** سجل يربط اشتراكاً معيناً بمصمم معين لفترة أسبوعية محددة.
5.  **Tag (المهمة/الفكرة):** نوع التصميم المطلوب تنفيذه (مثلاً: "بوست انستقرام"). له أهمية وتوقيت.

---

## 3. منطق العمل والخوارزميات (Core Business Logic)

يجب تنفيذ الخوارزميات التالية في الـ Backend (Services) لضمان عمل النظام بشكل آلي.

### أ. خوارزمية توزيع المصممين (Auto-Distribution Algorithm)
**الهدف:** تعيين مصمم لكل اشتراك نشط في بداية الأسبوع بشكل تلقائي.

**معايير المفاضلة (Scoring System):**
عند اختيار مصمم لاشتراك معين، يتم حساب نقاط لكل مصمم متاح بناءً على:
1.  **التخصص (30%):** هل تخصص المصمم يطابق تصنيف العميل؟
2.  **توازن السعة (30%):** الأولوية للمصمم الذي لديه فراغ أكبر (Current Load < Max Capacity).
3.  **التقييم (30%):** الأولوية للمصمم ذو التقييم الأعلى.
4.  **الاستمرارية (15%):** هل عمل المصمم مع هذا العميل في الأسبوع السابق؟ (للحفاظ على سياق العمل).
5.  **الخبرة (10%):** الاشتراكات ذات العدد الكبير من التصاميم تتطلب مصممين ذوي خبرة عالية.

```mermaid
flowchart TD
    Start(["Start Auto Distribution"]) --> GetSubs["Get Active Subscriptions"]
    GetSubs --> FilterSubs["Filter Unassigned Subscriptions"]
    FilterSubs --> SortSubs["Sort by Design Count (Desc)"]
    
    SortSubs --> LoopSubs{"Loop Subscriptions"}
    LoopSubs -- No More --> End(["End & Return Stats"])
    
    LoopSubs -- Next Sub --> GetDesigners["Get Available Designers"]
    GetDesigners --> FilterCap["Filter by Available Capacity"]
    
    FilterCap --> LoopDes{"Loop Designers"}
    LoopDes -- Next Des --> CalcScore["Calculate Score (Weights)"]
    CalcScore --> LoopDes
    
    LoopDes -- Done --> PickBest["Pick Designer with Max Score"]
    PickBest --> IsFound{"Designer Found?"}
    
    IsFound -- Yes --> Assign["Create ClientDesigner Record"]
    Assign --> UpdateLoad["Update Designer Load"]
    UpdateLoad --> LoopSubs
    
    IsFound -- No --> LogFail["Log Failure Reason"]
    LogFail --> LoopSubs
```

### ب. خوارزمية توزيع المهام (Tag Distribution Algorithm)
**الهدف:** بعد تعيين المصممين، يتم توزيع "التاقات" (أفكار التصاميم) على أيام الأسبوع.

**قواعد التوزيع:**
1.  **مصدر التاقات:**
    *   إذا كان الاشتراك "أساسي": نأخذ التاقات من تصنيف العميل (`Category Tags`).
    *   إذا كان الاشتراك "ثانوي": نأخذ التاقات المربوطة بالاشتراك نفسه (`Subscription Tags`).
2.  **الأولوية:** يتم توزيع التاقات ذات الأهمية `Very High` أولاً، ثم `High`، وهكذا.
3.  **قيد التكرار (للاشتراك الأساسي):**
    *   يمنع تكرار التاقات `Very High` (مسموح مرة واحدة فقط).
    *   يسمح بتكرار `Medium` و `Low` لملء الجدول إذا لزم الأمر.
4.  **التوقيت:**
    *   التاقات المرتبطة بيوم محدد (مثلاً "الجمعة") أو تاريخ سنوي (مثلاً "1 يناير") يجب وضعها في تاريخها بدقة.
    *   باقي التاقات توزع بالتتابع (Round Robin) على أيام العمل.

```mermaid
flowchart TD
    StartTags(["Start Tag Distribution"]) --> GetAssigns["Get Weekly Assignments"]
    GetAssigns --> LoopAssign{"Loop Assignments"}
    
    LoopAssign -- Next --> GetSource["Get Tags Source (Cat/Sub)"]
    GetSource --> SortTags["Sort Tags by Importance"]
    
    SortTags --> LoopSlots{"While Distributed < Required Count"}
    LoopSlots -- Next Slot --> PickTag["Pick Next Tag"]
    
    PickTag --> CheckImp{"Is Very High?"}
    CheckImp -- Yes --> CheckLimit{"Already Has Very High?"}
    CheckLimit -- Yes --> SkipTag["Skip Tag"]
    SkipTag --> LoopSlots
    
    CheckLimit -- No --> CheckDate{"Has Fixed Date?"}
    CheckDate -- Yes --> AssignDate["Assign to Specific Date"]
    CheckDate -- No --> AssignRR["Assign Round Robin"]
    
    AssignDate --> Save["Save ClientTagDistribution"]
    AssignRR --> Save
    
    Save --> LoopSlots
    
    LoopSlots -- Done --> LoopAssign
    LoopAssign -- Done --> EndTags(["End"])
```

---

## 4. متطلبات لوحة التحكم (Admin Panel Requirements)
المطلوب بناء واجهة ويب (Dashboard) تمكن الإدارة من:

1.  **إدارة الموارد (CRUD):** إضافة وتعديل وحذف (العملاء، المصممين، الاشتراكات، التاقات).
2.  **شاشة توزيع المصممين:**
    *   عرض جدول التوزيع للأسبوع الحالي.
    *   زر لتشغيل "التوزيع التلقائي".
    *   إمكانية التعديل اليدوي (سحب وإفلات أو قائمة منسدلة لتغيير المصمم).
3.  **شاشة جدول المهام:**
    *   عرض تقويم (Calendar View) لكل عميل يوضح التاقات الموزعة على الأيام.
    *   زر لتشغيل "توزيع التاقات".

## 5. ملاحظات التطوير (Development Notes)
*   يجب أن يكون النظام قابلاً للتوسع (Scalable) لدعم آلاف العملاء.
*   يجب كتابة **Unit Tests** للخوارزميات المذكورة أعلاه لضمان دقة النتائج.
*   يفضل جعل "أوزان التوزيع" (Weights) قابلة للتعديل من لوحة التحكم (Configurable) وليس ثابتة في الكود.
