(() => {
    "use strict";

    const doc = document;
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const baseUrl = doc.body?.dataset.baseUrl || `${window.location.origin}/`;
    const appUrl = (path) => new URL(String(path).replace(/^\//, ""), baseUrl).toString();

    const $ = (selector, scope = doc) => scope.querySelector(selector);
    const $$ = (selector, scope = doc) => Array.from(scope.querySelectorAll(selector));

    const el = (tag, className = null, text = null) => {
        const node = doc.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== null) {
            node.textContent = text;
        }

        return node;
    };

    const svgIcon = (id) => {
        const namespace = "http://www.w3.org/2000/svg";
        const svg = doc.createElementNS(namespace, "svg");
        const use = doc.createElementNS(namespace, "use");
        svg.setAttribute("class", "icon");
        svg.setAttribute("aria-hidden", "true");
        use.setAttribute("href", `#${id}`);
        svg.append(use);

        return svg;
    };

    const normalize = (value) =>
        String(value || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .trim();

    const initials = (doctor) => {
        const names = String(doctor.nombres || "").trim();
        const surnames = String(doctor.apellidos || "").trim();
        return `${names.charAt(0)}${surnames.charAt(0)}`.toUpperCase() || "VS";
    };

    const pad = (number) => String(number).padStart(2, "0");

    const reservaUrl = (doctor, especialidadId = null) => {
        const params = new URLSearchParams();
        params.set("medico", String(doctor.id));

        const specialty = especialidadId || doctor.especialidades?.[0]?.id;

        if (specialty) {
            params.set("especialidad", String(specialty));
        }

        return appUrl(`reservar?${params.toString()}`);
    };

    const api = async (path, options = {}) => {
        const headers = {
            Accept: "application/json",
            ...(options.body ? { "Content-Type": "application/json" } : {}),
            ...(options.headers || {}),
        };

        const response = await fetch(appUrl(path), {
            credentials: "same-origin",
            ...options,
            headers,
        });

        const payload = await response.json().catch(() => null);

        return { response, payload };
    };

    /* Header state -------------------------------------------------------- */

    const header = $("[data-site-header]");

    if (header) {
        const sentinel = doc.createElement("div");
        sentinel.setAttribute("aria-hidden", "true");
        sentinel.style.cssText =
            "position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none;";
        doc.body.append(sentinel);

        const headerObserver = new IntersectionObserver(
            ([entry]) => {
                header.classList.toggle("is-scrolled", !entry.isIntersecting);
            },
            { threshold: 0 }
        );

        headerObserver.observe(sentinel);
    }

    /* Mobile menu --------------------------------------------------------- */

    const navToggle = $("[data-nav-toggle]");
    const mobileMenu = $("[data-mobile-menu]");
    const menuClose = $("[data-menu-close]");
    let lastFocused = null;

    const menuFocusables = () => {
        if (!mobileMenu) {
            return [];
        }

        return $$("a, button", mobileMenu).filter(
            (node) => !node.hasAttribute("disabled") && node.offsetParent !== null
        );
    };

    const openMenu = () => {
        if (!navToggle || !mobileMenu) {
            return;
        }

        lastFocused = doc.activeElement;
        mobileMenu.classList.add("is-open");
        mobileMenu.setAttribute("aria-hidden", "false");
        navToggle.setAttribute("aria-expanded", "true");
        navToggle.querySelector(".sr-only").textContent = "Cerrar menú";
        doc.body.classList.add("menu-open");
        window.setTimeout(() => menuClose?.focus(), 50);
    };

    const closeMenu = (restoreFocus = true) => {
        if (!navToggle || !mobileMenu) {
            return;
        }

        mobileMenu.classList.remove("is-open");
        mobileMenu.setAttribute("aria-hidden", "true");
        navToggle.setAttribute("aria-expanded", "false");
        navToggle.querySelector(".sr-only").textContent = "Abrir menú";
        doc.body.classList.remove("menu-open");

        if (restoreFocus && lastFocused instanceof HTMLElement) {
            lastFocused.focus();
        }
    };

    navToggle?.addEventListener("click", () => {
        if (mobileMenu?.classList.contains("is-open")) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    menuClose?.addEventListener("click", () => closeMenu());

    mobileMenu?.addEventListener("click", (event) => {
        if (event.target.closest("a")) {
            closeMenu(false);
        }
    });

    doc.addEventListener("keydown", (event) => {
        if (!mobileMenu?.classList.contains("is-open")) {
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            closeMenu();
            return;
        }

        if (event.key !== "Tab") {
            return;
        }

        const focusable = menuFocusables();

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && doc.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && doc.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 1020 && mobileMenu?.classList.contains("is-open")) {
            closeMenu(false);
        }
    });

    /* Reveal sections on enter --------------------------------------------- */

    const revealSections = $$("[data-section-reveal]");

    revealSections.forEach((section) => {
        $$(".reveal", section).forEach((node, index) => {
            node.style.setProperty("--reveal-delay", `${Math.min(index * 70, 350)}ms`);
        });
    });

    if (revealSections.length > 0) {
        if (reduceMotion || !("IntersectionObserver" in window)) {
            revealSections.forEach((section) => section.classList.add("is-revealed"));
        } else {
            const revealObserver = new IntersectionObserver(
                (entries, observer) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add("is-revealed");
                        observer.unobserve(entry.target);
                    });
                },
                { threshold: 0.08, rootMargin: "0px 0px -6% 0px" }
            );

            revealSections.forEach((section) => revealObserver.observe(section));
        }
    }

    /* Specialty search ---------------------------------------------------- */

    const searchInput = $("[data-specialty-search]");
    const specialtyItems = $$("[data-specialty-item]");
    const specialtyCount = $("[data-specialty-count]");
    const specialtyEmpty = $("[data-specialty-empty]");

    if (searchInput && specialtyItems.length > 0) {
        const filterSpecialties = () => {
            const term = normalize(searchInput.value);
            let visible = 0;

            specialtyItems.forEach((item) => {
                const matches = term === "" || normalize(item.dataset.search || "").includes(term);
                item.hidden = !matches;

                if (matches) {
                    visible += 1;
                }
            });

            if (specialtyCount) {
                specialtyCount.textContent = `${visible} ${
                    visible === 1 ? "especialidad" : "especialidades"
                }`;
            }

            if (specialtyEmpty) {
                specialtyEmpty.hidden = visible !== 0;
            }
        };

        searchInput.addEventListener("input", filterSpecialties);
        searchInput.addEventListener("search", filterSpecialties);
    }

    /* Doctor directory ---------------------------------------------------- */

    const doctorGrid = $("[data-doctor-grid]");
    const doctorCount = $("[data-doctors-count]");
    const doctorReset = $("[data-doctors-reset]");
    const doctorFilters = $$("[data-doctor-filter]");
    let doctorRequest = 0;

    const setDoctorFilterState = (especialidadId = null) => {
        const activeFilter = especialidadId === null ? "all" : String(especialidadId);

        doctorFilters.forEach((button) => {
            const isActive = button.dataset.doctorFilter === activeFilter;
            button.classList.toggle("is-active", isActive);
            button.setAttribute("aria-pressed", isActive ? "true" : "false");
        });

        if (doctorReset) {
            doctorReset.hidden = especialidadId === null;
        }
    };

    const doctorSkeleton = () => {
        const row = el("div", "doctor-row skeleton-row");

        const index = el("span", "skeleton");
        index.style.width = "18px";
        index.style.height = "8px";

        const avatar = el("span", "skeleton skeleton-avatar");
        const main = el("div");
        main.append(el("span", "skeleton skeleton-line"), el("span", "skeleton skeleton-line short"));
        const pill = el("span", "skeleton skeleton-pill");

        row.append(index, avatar, main, pill);

        return row;
    };

    const doctorRow = (doctor, index, specialtyId) => {
        const row = el("article", "doctor-row");
        row.dataset.doctorId = String(doctor.id);

        const number = el("span", "doctor-index", pad(index + 1));
        number.setAttribute("aria-hidden", "true");

        const avatar = el("span", "doctor-avatar", initials(doctor));
        avatar.setAttribute("aria-hidden", "true");

        const main = el("div", "doctor-main");
        const name = el(
            "h3",
            null,
            doctor.nombre_completo || `Dr. ${doctor.nombres || ""} ${doctor.apellidos || ""}`
        );
        const tags = el("ul", "doctor-tags");
        tags.setAttribute("aria-label", "Especialidades");

        (doctor.especialidades || []).forEach((specialty) => {
            tags.append(el("li", null, specialty.nombre));
        });

        main.append(name, tags);

        const meta = el("div", "doctor-meta");
        const duration = el("span", "doctor-duration");
        duration.append(svgIcon("i-clock"), doc.createTextNode(` ${doctor.duracion_cita_min || 30} min`));

        const link = el("a", "doctor-link");
        link.href = reservaUrl(doctor, specialtyId);
        link.setAttribute(
            "aria-label",
            `Agendar cita con ${
                doctor.nombre_completo || `Dr. ${doctor.nombres || ""} ${doctor.apellidos || ""}`
            }`
        );
        link.append(el("span", null, "Agendar"), svgIcon("i-arrow-up-right"));

        meta.append(duration, link);
        row.append(number, avatar, main, meta);

        return row;
    };

    const setDoctorStatus = (text) => {
        if (doctorCount) {
            doctorCount.textContent = text;
        }
    };

    const showDoctorError = (message, retry) => {
        if (!doctorGrid) {
            return;
        }

        const box = el("div", "empty-state empty-state-inline");
        box.append(el("strong", null, message));
        box.append(el("p", null, "Revisa tu conexión o inténtalo de nuevo."));

        const button = el("button", "btn btn-outline btn-sm", "Reintentar");
        button.type = "button";
        button.addEventListener("click", retry);
        box.append(button);

        doctorGrid.replaceChildren(box);
        doctorGrid.setAttribute("aria-busy", "false");
    };

    const showDoctorEmpty = (name = null) => {
        if (!doctorGrid) {
            return;
        }

        const box = el("div", "empty-state empty-state-inline");
        box.append(
            el(
                "strong",
                null,
                name
                    ? `No hay médicos disponibles en ${name}.`
                    : "No hay médicos disponibles por el momento."
            )
        );
        box.append(el("p", null, "Prueba con otra especialidad o reserva directamente."));

        const link = el("a", "btn btn-outline btn-sm", "Reservar cita");
        link.href = appUrl("reservar");
        box.append(link);

        doctorGrid.replaceChildren(box);
        doctorGrid.setAttribute("aria-busy", "false");
    };

    const loadDoctors = async (especialidadId = null, specialtyName = null) => {
        if (!doctorGrid) {
            return;
        }

        const request = ++doctorRequest;

        setDoctorFilterState(especialidadId);
        doctorGrid.setAttribute("aria-busy", "true");
        doctorGrid.replaceChildren(doctorSkeleton(), doctorSkeleton(), doctorSkeleton());
        setDoctorStatus("Cargando médicos…");

        const params = new URLSearchParams();

        if (especialidadId) {
            params.set("especialidad", String(especialidadId));
        }

        try {
            const { response, payload } = await api(
                `api/public/medicos${params.toString() ? `?${params.toString()}` : ""}`
            );

            if (request !== doctorRequest) {
                return;
            }

            if (!response.ok || !payload?.ok) {
                throw new Error(payload?.error?.message || "No pudimos cargar los médicos.");
            }

            const doctors = Array.isArray(payload.data) ? payload.data : [];

            if (doctors.length === 0) {
                setDoctorStatus("Sin resultados");
                showDoctorEmpty(specialtyName);
                return;
            }

            const rows = doctors.map((doctor, index) => doctorRow(doctor, index, especialidadId));
            doctorGrid.replaceChildren(...rows);
            doctorGrid.setAttribute("aria-busy", "false");
            setDoctorStatus(
                `${doctors.length} ${doctors.length === 1 ? "médico" : "médicos"}${
                    specialtyName ? ` · ${specialtyName}` : " activos"
                }`
            );
        } catch (error) {
            if (request !== doctorRequest) {
                return;
            }

            setDoctorStatus("No se pudo cargar");
            showDoctorError(
                error instanceof Error ? error.message : "No pudimos cargar los médicos.",
                () => loadDoctors(especialidadId, specialtyName)
            );
        }
    };

    $$("[data-especialidad-id]").forEach((link) => {
        link.addEventListener("click", (event) => {
            if (!doctorGrid) {
                return;
            }

            event.preventDefault();

            const id = link.dataset.especialidadId;
            const name = link.dataset.especialidadNombre || "la especialidad";

            $$("[data-especialidad-id]").forEach((item) => item.classList.remove("is-active"));
            link.classList.add("is-active");

            loadDoctors(id, name);

            const section = doc.getElementById("medicos");

            if (section && section.getBoundingClientRect().top > window.innerHeight * 0.45) {
                section.scrollIntoView({
                    behavior: reduceMotion ? "auto" : "smooth",
                    block: "start",
                });
            }
        });
    });

    doctorFilters.forEach((button) => {
        button.addEventListener("click", () => {
            const isAll = button.dataset.doctorFilter === "all";
            const especialidadId = isAll ? null : button.dataset.doctorFilter;
            const especialidadNombre = isAll ? null : button.dataset.doctorFilterName || null;

            $$("[data-especialidad-id]").forEach((item) => {
                item.classList.toggle(
                    "is-active",
                    !isAll && item.dataset.especialidadId === String(especialidadId)
                );
            });

            loadDoctors(especialidadId, especialidadNombre);
        });
    });

    doctorReset?.addEventListener("click", () => {
        $$("[data-especialidad-id]").forEach((item) => item.classList.remove("is-active"));
        loadDoctors();
    });

    /* Reservation wizard --------------------------------------------------- */

    const reservaApp = $("[data-reserva-app]");

    if (reservaApp) {
        const form = $("[data-reserva-form]", reservaApp);
        const card = $("[data-reserva-card]", reservaApp);
        const successPanel = $("[data-reserva-success]", reservaApp);
        const steps = $$("[data-step]", reservaApp);
        const indicators = $$("[data-step-indicator]", reservaApp);
        const specialtyInputs = $$("[data-especialidad-choice]", reservaApp);
        const doctorList = $("[data-reserva-medicos]", reservaApp);
        const doctorStatus = $("[data-doctor-picker-status]", reservaApp);
        const dateInput = $("[data-reserva-fecha]", reservaApp);
        const slotsGrid = $("[data-reserva-slots]", reservaApp);
        const slotsStatus = $("[data-slots-status]", reservaApp);
        const alertBox = $("[data-reserva-alert]", reservaApp);
        const submitButton = $("[data-reserva-submit]", reservaApp);
        const reasonInput = $("[data-reserva-motivo]", reservaApp);
        const reasonCount = $("[data-motivo-count]", reservaApp);
        const nextButtons = $$("[data-next-step]", reservaApp);

        if (!form || !card || !successPanel || steps.length === 0) {
            return;
        }

        const summaryNodes = {
            especialidad: $("[data-summary-especialidad]", reservaApp),
            medico: $("[data-summary-medico]", reservaApp),
            fecha: $("[data-summary-fecha]", reservaApp),
            hora: $("[data-summary-hora]", reservaApp),
        };

        const confirmNodes = {
            especialidad: $("[data-confirm-especialidad]", reservaApp),
            medico: $("[data-confirm-medico]", reservaApp),
            fecha: $("[data-confirm-fecha]", reservaApp),
            hora: $("[data-confirm-hora]", reservaApp),
        };

        const state = {
            step: 1,
            especialidadId: null,
            especialidadNombre: "",
            medicoId: null,
            medicoNombre: "",
            medicoDuracion: null,
            fecha: "",
            hora: "",
        };

        let doctorToken = 0;
        let slotToken = 0;
        let submitting = false;

        const isoDate = (date) =>
            `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

        const startDate = new Date();
        startDate.setHours(12, 0, 0, 0);
        const maxDate = new Date(startDate);
        maxDate.setDate(maxDate.getDate() + 60);

        if (dateInput) {
            dateInput.min = isoDate(startDate);
            dateInput.max = isoDate(maxDate);
        }

        const nextBusinessDay = () => {
            const date = new Date(startDate);

            do {
                date.setDate(date.getDate() + 1);
            } while (date.getDay() === 0 || date.getDay() === 6);

            return isoDate(date);
        };

        const parseDate = (iso) => new Date(`${iso}T12:00:00`);

        const longDate = (iso) => {
            if (!iso) {
                return "";
            }

            return new Intl.DateTimeFormat("es-BO", {
                weekday: "long",
                day: "numeric",
                month: "long",
            })
                .format(parseDate(iso))
                .replace(/^./, (letter) => letter.toUpperCase());
        };

        const setAlert = (message = "", type = "error", action = null) => {
            if (!alertBox) {
                return;
            }

            alertBox.hidden = message === "";
            alertBox.classList.toggle("is-info", type === "info");
            alertBox.replaceChildren();

            if (message === "") {
                return;
            }

            alertBox.append(doc.createTextNode(message));

            if (action) {
                const link = el("a", null, action.label);
                link.href = action.href;
                alertBox.append(doc.createTextNode(" "), link);
            }
        };

        const revealAlert = () => {
            if (!alertBox || alertBox.hidden) {
                return;
            }

            card?.scrollIntoView({
                behavior: reduceMotion ? "auto" : "smooth",
                block: "start",
            });
        };

        const setSummaryValue = (node, value) => {
            if (!node) {
                return;
            }

            node.textContent = value || "Por elegir";
            node.classList.toggle("is-empty", !value);
        };

        const updateSummary = () => {
            const values = {
                especialidad: state.especialidadNombre,
                medico: state.medicoNombre,
                fecha: state.fecha ? longDate(state.fecha) : "",
                hora: state.hora,
            };

            Object.entries(summaryNodes).forEach(([key, node]) => {
                setSummaryValue(node, values[key]);
            });

            Object.entries(confirmNodes).forEach(([key, node]) => {
                setSummaryValue(node, values[key]);
            });
        };

        const updateNextButtons = () => {
            nextButtons.forEach((button) => {
                const target = Number(button.dataset.nextStep);

                if (target === 2) {
                    button.disabled = !(state.especialidadId && state.medicoId);
                } else if (target === 3) {
                    button.disabled = !(state.fecha && state.hora);
                }
            });
        };

        const markChoice = (input) => {
            $$(".choice-card", reservaApp).forEach((cardNode) => {
                cardNode.classList.toggle("is-selected", cardNode.contains(input));
            });
        };

        const markDoctor = (input) => {
            $$(".doctor-choice", reservaApp).forEach((choice) => {
                choice.classList.toggle("is-selected", choice.contains(input));
            });
        };

        const clearSlotSelection = () => {
            state.hora = "";
            $$(".slot", slotsGrid).forEach((slot) => {
                slot.classList.remove("is-selected");
                slot.setAttribute("aria-pressed", "false");
            });
            updateSummary();
            updateNextButtons();
        };

        const clearSlots = (message = "Elige una fecha para ver horarios.") => {
            if (!slotsGrid) {
                return;
            }

            state.hora = "";
            slotsGrid.replaceChildren(el("div", "slots-empty", message));

            if (slotsStatus) {
                slotsStatus.textContent = "Sin horarios";
            }

            updateSummary();
            updateNextButtons();
        };

        const slotButton = (slot) => {
            const button = el("button", "slot", slot.hora);
            button.type = "button";
            button.dataset.hora = slot.hora;
            button.disabled = slot.disponible !== true;
            button.setAttribute("aria-pressed", "false");
            button.title = slot.disponible
                ? "Horario disponible"
                : "Horario no disponible";

            button.addEventListener("click", () => {
                state.hora = slot.hora;
                $$(".slot", slotsGrid).forEach((node) => {
                    const active = node === button;
                    node.classList.toggle("is-selected", active);
                    node.setAttribute("aria-pressed", active ? "true" : "false");
                });
                updateSummary();
                updateNextButtons();
            });

            return button;
        };

        const loadSlots = async () => {
            if (!slotsGrid) {
                return;
            }

            if (!state.medicoId || !state.fecha) {
                clearSlots(
                    state.medicoId
                        ? "Elige una fecha para ver horarios."
                        : "Primero elige un médico."
                );
                return;
            }

            const token = ++slotToken;
            slotsGrid.replaceChildren();

            const loading = el("div", "slots-loading");
            for (let index = 0; index < 8; index += 1) {
                loading.append(el("span", "skeleton"));
            }
            slotsGrid.append(loading);

            if (slotsStatus) {
                slotsStatus.textContent = "Consultando agenda…";
            }

            state.hora = "";
            updateSummary();
            updateNextButtons();

            const params = new URLSearchParams({
                medico: String(state.medicoId),
                fecha: state.fecha,
            });

            try {
                const { response, payload } = await api(
                    `api/public/disponibilidad?${params.toString()}`
                );

                if (token !== slotToken) {
                    return;
                }

                if (!response.ok || !payload?.ok) {
                    throw new Error(payload?.error?.message || "No pudimos cargar la agenda.");
                }

                const slots = Array.isArray(payload.data?.slots) ? payload.data.slots : [];
                const available = slots.filter((slot) => slot.disponible === true).length;

                if (slots.length === 0) {
                    slotsGrid.replaceChildren(
                        el(
                            "div",
                            "slots-empty",
                            "No hay atención para esta fecha. Prueba con otro día."
                        )
                    );
                    if (slotsStatus) {
                        slotsStatus.textContent = "Sin horarios";
                    }
                    return;
                }

                slotsGrid.replaceChildren(...slots.map(slotButton));

                if (slotsStatus) {
                    slotsStatus.textContent =
                        available === 0
                            ? "Sin cupos disponibles"
                            : `${available} ${available === 1 ? "cupo" : "cupos"}`;
                }
            } catch (error) {
                if (token !== slotToken) {
                    return;
                }

                const wrapper = el("div", "slots-empty");
                wrapper.append(
                    doc.createTextNode(
                        error instanceof Error
                            ? `${error.message} `
                            : "No pudimos cargar los horarios. "
                    )
                );

                const retry = el("button", "link-button", "Reintentar");
                retry.type = "button";
                retry.addEventListener("click", loadSlots);
                wrapper.append(retry);
                slotsGrid.replaceChildren(wrapper);

                if (slotsStatus) {
                    slotsStatus.textContent = "No disponible";
                }
            }
        };

        const doctorLoading = () => {
            const wrapper = el("div", "picker-loading");
            wrapper.append(el("span", "picker-loader"), doc.createTextNode("Buscando médicos…"));
            return wrapper;
        };

        const doctorChoice = (doctor) => {
            const label = el("label", "doctor-choice");
            const input = doc.createElement("input");
            input.type = "radio";
            input.name = "medico_id";
            input.value = String(doctor.id);
            input.dataset.medicoChoice = "";
            input.dataset.nombre =
                doctor.nombre_completo ||
                `Dr. ${doctor.nombres || ""} ${doctor.apellidos || ""}`.trim();
            input.dataset.duracion = String(doctor.duracion_cita_min || 30);
            input.dataset.especialidades = (doctor.especialidades || [])
                .map((specialty) => specialty.nombre)
                .join(" · ");

            const avatar = el("span", "doctor-choice-avatar", initials(doctor));
            avatar.setAttribute("aria-hidden", "true");

            const main = el("span", "doctor-choice-main");
            const name = el("strong", null, input.dataset.nombre);
            const specialties = el(
                "span",
                null,
                input.dataset.especialidades || "Atención médica"
            );
            main.append(name, specialties);

            const meta = el(
                "span",
                "doctor-choice-meta",
                `${doctor.duracion_cita_min || 30} min`
            );

            label.append(input, avatar, main, meta);

            input.addEventListener("change", () => {
                if (!input.checked) {
                    return;
                }

                state.medicoId = Number(input.value);
                state.medicoNombre = input.dataset.nombre;
                state.medicoDuracion = Number(input.dataset.duracion);
                markDoctor(input);
                updateSummary();
                updateNextButtons();

                if (state.step === 2) {
                    loadSlots();
                }
            });

            return label;
        };

        const loadReservaDoctors = async (prefillMedico = null) => {
            if (!doctorList) {
                return;
            }

            if (!state.especialidadId) {
                doctorList.replaceChildren(
                    el("div", "picker-empty", "Primero elige una especialidad.")
                );
                if (doctorStatus) {
                    doctorStatus.textContent = "Elige una especialidad";
                }
                return;
            }

            const token = ++doctorToken;
            doctorList.replaceChildren(doctorLoading());

            if (doctorStatus) {
                doctorStatus.textContent = "Cargando médicos…";
            }

            state.medicoId = null;
            state.medicoNombre = "";
            state.medicoDuracion = null;
            updateSummary();
            updateNextButtons();

            try {
                const { response, payload } = await api(
                    `api/public/medicos?especialidad=${encodeURIComponent(
                        String(state.especialidadId)
                    )}`
                );

                if (token !== doctorToken) {
                    return;
                }

                if (!response.ok || !payload?.ok) {
                    throw new Error(payload?.error?.message || "No pudimos cargar los médicos.");
                }

                const doctors = Array.isArray(payload.data) ? payload.data : [];

                if (doctors.length === 0) {
                    doctorList.replaceChildren(
                        el("div", "picker-empty", "No hay médicos disponibles para esta especialidad.")
                    );
                    if (doctorStatus) {
                        doctorStatus.textContent = "Sin médicos";
                    }
                    return;
                }

                const choices = doctors.map(doctorChoice);
                doctorList.replaceChildren(...choices);

                if (doctorStatus) {
                    doctorStatus.textContent = `${doctors.length} ${
                        doctors.length === 1 ? "médico" : "médicos"
                    }`;
                }

                if (prefillMedico) {
                    const prefill = doctorList.querySelector(
                        `input[name="medico_id"][value="${Number(prefillMedico)}"]`
                    );

                    if (prefill) {
                        prefill.checked = true;
                        prefill.dispatchEvent(new Event("change", { bubbles: true }));
                    }
                }
            } catch (error) {
                if (token !== doctorToken) {
                    return;
                }

                const wrapper = el("div", "picker-empty");
                wrapper.append(
                    doc.createTextNode(
                        error instanceof Error
                            ? `${error.message} `
                            : "No pudimos cargar los médicos. "
                    )
                );

                const retry = el("button", "link-button", "Reintentar");
                retry.type = "button";
                retry.addEventListener("click", () => loadReservaDoctors(prefillMedico));
                wrapper.append(retry);
                doctorList.replaceChildren(wrapper);

                if (doctorStatus) {
                    doctorStatus.textContent = "No disponible";
                }
            }
        };

        const applySpecialty = async (input, prefillMedico = null) => {
            state.especialidadId = Number(input.value);
            state.especialidadNombre = input.dataset.nombre || "";
            state.medicoId = null;
            state.medicoNombre = "";
            state.medicoDuracion = null;
            state.hora = "";

            markChoice(input);
            updateSummary();

            if (slotsGrid) {
                clearSlots("Elige un médico y una fecha para ver horarios.");
            }

            await loadReservaDoctors(prefillMedico);
        };

        const goToStep = (step, scroll = true) => {
            const target = Math.min(3, Math.max(1, Number(step)));
            state.step = target;

            steps.forEach((stepNode) => {
                const active = Number(stepNode.dataset.step) === target;
                stepNode.hidden = !active;
                stepNode.classList.toggle("is-active", active);
            });

            indicators.forEach((indicator) => {
                const number = Number(indicator.dataset.stepIndicator);
                indicator.classList.toggle("is-active", number === target);
                indicator.classList.toggle("is-complete", number < target);

                if (number === target) {
                    indicator.setAttribute("aria-current", "step");
                } else {
                    indicator.removeAttribute("aria-current");
                }
            });

            if (target === 2) {
                if (dateInput && !dateInput.value) {
                    dateInput.value = nextBusinessDay();
                }

                state.fecha = dateInput?.value || "";
                updateSummary();
                loadSlots();
            }

            if (target === 3) {
                updateSummary();
            }

            updateNextButtons();
            setAlert("");

            const activeStep = steps.find((stepNode) => Number(stepNode.dataset.step) === target);

            if (scroll) {
                activeStep?.querySelector("h2")?.focus({ preventScroll: true });
                card.scrollIntoView({
                    behavior: reduceMotion ? "auto" : "smooth",
                    block: "start",
                });
            }
        };

        const showSuccess = (data) => {
            const cita = data?.cita || {};
            const ficha = data?.ficha || {};

            const values = {
                "[data-success-cita]": cita.codigo || "—",
                "[data-success-ficha]": ficha.codigo || "—",
                "[data-success-fecha]": cita.fecha ? longDate(cita.fecha) : "—",
                "[data-success-hora]": cita.hora_inicio
                    ? `${cita.hora_inicio}–${cita.hora_fin || ""}`
                    : "—",
                "[data-success-medico]": state.medicoNombre || "—",
            };

            Object.entries(values).forEach(([selector, value]) => {
                const node = $(selector, reservaApp);
                if (node) {
                    node.textContent = value;
                }
            });

            card.hidden = true;
            successPanel.hidden = false;
            successPanel.scrollIntoView({
                behavior: reduceMotion ? "auto" : "smooth",
                block: "center",
            });
            successPanel.querySelector("h2")?.focus({ preventScroll: true });
        };

        specialtyInputs.forEach((input) => {
            input.addEventListener("change", () => {
                if (input.checked) {
                    applySpecialty(input);
                }
            });
        });

        nextButtons.forEach((button) => {
            button.addEventListener("click", () => goToStep(Number(button.dataset.nextStep)));
        });

        $$("[data-prev-step]", reservaApp).forEach((button) => {
            button.addEventListener("click", () => {
                goToStep(state.step - 1);
            });
        });

        dateInput?.addEventListener("change", () => {
            state.fecha = dateInput.value;
            state.hora = "";
            updateSummary();
            updateNextButtons();
            loadSlots();
        });

        reasonInput?.addEventListener("input", () => {
            if (reasonCount) {
                reasonCount.textContent = String(reasonInput.value.length);
            }
        });

        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            if (submitting) {
                return;
            }

            if (!state.especialidadId || !state.medicoId || !state.fecha || !state.hora) {
                setAlert("Completa especialidad, médico, fecha y hora antes de confirmar.");
                revealAlert();
                return;
            }

            submitting = true;
            setAlert("");
            submitButton?.classList.add("is-loading");
            submitButton?.setAttribute("aria-busy", "true");
            if (submitButton) {
                submitButton.disabled = true;
            }

            let completed = false;

            try {
                const { response, payload } = await api("api/public/reservar", {
                    method: "POST",
                    body: JSON.stringify({
                        especialidad_id: state.especialidadId,
                        medico_id: state.medicoId,
                        fecha: state.fecha,
                        hora: state.hora,
                        motivo: reasonInput?.value?.trim() || "",
                    }),
                });

                if (response.status === 401) {
                    setAlert(
                        "Tu sesión expiró. Inicia sesión otra vez para confirmar la cita.",
                        "error",
                        { label: "Iniciar sesión", href: appUrl("login") }
                    );
                    revealAlert();
                    return;
                }

                if (response.status === 409) {
                    goToStep(2);
                    setAlert("Ese horario acaba de ocuparse. Elige otro horario disponible.");
                    return;
                }

                if (!response.ok || !payload?.ok) {
                    const fields = payload?.error?.fields || {};
                    const firstField = Object.values(fields)[0];
                    const message =
                        firstField ||
                        payload?.error?.message ||
                        "No pudimos confirmar la cita. Revisa los datos e inténtalo de nuevo.";

                    if (fields.fecha || fields.hora) {
                        goToStep(2);
                    } else if (fields.medico_id || fields.especialidad_id) {
                        goToStep(1);
                    }

                    setAlert(message);
                    revealAlert();
                    return;
                }

                const data = payload.data || {};
                completed = true;
                showSuccess(data);
            } catch (error) {
                setAlert(
                    "No pudimos conectar con el servidor. Revisa tu conexión e inténtalo de nuevo."
                );
                revealAlert();
            } finally {
                submitting = false;

                if (!completed) {
                    submitButton?.classList.remove("is-loading");
                    submitButton?.removeAttribute("aria-busy");
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            }
        });

        const query = new URLSearchParams(window.location.search);
        const initialSpecialty = query.get("especialidad");
        const initialDoctor = query.get("medico");
        const initialInput = initialSpecialty
            ? specialtyInputs.find((input) => input.value === initialSpecialty)
            : null;

        if (initialInput) {
            initialInput.checked = true;
            applySpecialty(initialInput, initialDoctor).then(() => {
                if (initialDoctor && state.medicoId) {
                    goToStep(2, false);
                } else {
                    goToStep(1, false);
                }
            });
        } else {
            goToStep(1, false);
            clearSlots("Elige especialidad y médico para ver horarios.");
        }

        updateSummary();
        updateNextButtons();

        if (reasonInput && reasonCount) {
            reasonCount.textContent = String(reasonInput.value.length);
        }
    }
})();
