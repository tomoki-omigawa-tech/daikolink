<style>[x-cloak] { display: none !important; }</style>
<script>
    window.cellEditor = (config) => ({
        config,
        open: false,
        saving: false,
        errors: [],
        pos: { top: 0, left: 0 },
        target: { userId: null, period: null, seat: 1 },
        mode: 'secondary',
        secondary: { grade: '', subject_id: '' },
        first: { grade: '', subject_id: '' },
        second: { grade: '', subject_id: '' },

        init() {
            const saved = sessionStorage.getItem(this.scrollKey());
            if (saved) {
                const [left, top] = saved.split(',').map(Number);
                this.$refs.scroller.scrollLeft = left;
                window.scrollTo(0, top);
                sessionStorage.removeItem(this.scrollKey());
            }
        },
        scrollKey() {
            return 'grid-scroll:' + location.pathname + location.search;
        },
        periodInfo() {
            return this.config.periods[this.target.period] ?? { hasElementary: false, hasGroup: false };
        },
        teacher() {
            return this.config.teachers[this.target.userId] ?? { name: '', subjects: [] };
        },
        title() {
            return `${this.teacher().name}　${this.target.period}限・${this.target.seat === 1 ? '左' : '右'}の席`;
        },
        edit(event, userId, period, seat) {
            const state = this.config.state[`${userId}-${period}-${seat}`] ?? {};
            this.target = { userId, period, seat };
            this.mode = state.mode && state.mode !== 'empty' ? state.mode : 'secondary';
            this.secondary = { grade: '', subject_id: '', ...(state.secondary ?? {}) };
            this.first = { grade: '', subject_id: '', ...(state.first ?? {}) };
            this.second = { grade: '', subject_id: '', ...(state.second ?? {}) };
            this.errors = [];

            const rect = event.currentTarget.getBoundingClientRect();
            const width = 340;
            const height = 320;
            this.pos.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
            this.pos.top = rect.bottom + height < window.innerHeight ? rect.bottom + 4 : Math.max(8, rect.top - height - 4);
            this.open = true;
        },
        grades(elementary) {
            return this.config.grades.filter((grade) => grade.elementary === elementary);
        },
        subjectsFor(gradeValue) {
            const grade = this.config.grades.find((g) => g.value === gradeValue);
            return grade ? this.config.subjects.filter((s) => grade.stages.includes(s.stage)) : [];
        },
        resetSubject(lesson) {
            if (!this.subjectsFor(lesson.grade).some((s) => s.id === lesson.subject_id)) {
                lesson.subject_id = '';
            }
        },
        warn(subjectId) {
            return subjectId !== '' && !this.teacher().subjects.includes(subjectId);
        },
        async save(mode = this.mode) {
            this.saving = true;
            this.errors = [];
            const response = await fetch(this.config.updateUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    ...this.config.extra,
                    user_id: this.target.userId,
                    period: this.target.period,
                    seat: this.target.seat,
                    mode,
                    secondary: this.secondary,
                    first: this.first,
                    second: this.second,
                }),
            });
            this.saving = false;

            if (response.ok) {
                sessionStorage.setItem(this.scrollKey(), `${this.$refs.scroller.scrollLeft},${window.scrollY}`);
                location.reload();
                return;
            }
            const json = await response.json().catch(() => ({}));
            this.errors = json.errors ? Object.values(json.errors).flat() : ['保存できませんでした。'];
        },
    });
</script>
