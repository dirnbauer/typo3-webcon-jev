import { useCallback as e, useEffect as t, useMemo as n, useRef as r, useState as i } from "@webconsulting/shadcn-ui/react.js";
import { defineShadcnApp as a, ui as o, useTypo3 as s } from "@webconsulting/shadcn-ui/runtime.js";
import { Fragment as c, jsx as l, jsxs as u } from "@webconsulting/shadcn-ui/jsx-runtime.js";
//#region Build/Frontend/src/api.ts
function d(e, t) {
	let n = e[t];
	if (typeof n != "string" || n === "") throw Error(`The AJAX route "${t}" is not registered. Flush the backend caches.`);
	return n;
}
async function f(e) {
	if (!e.ok && e.status !== 422 && e.status !== 400 && e.status !== 404) throw Error(`The server answered ${e.status}.`);
	return await e.json();
}
function p(e) {
	let t = async (t, n) => f(await fetch(d(e, t), {
		method: "POST",
		headers: { "Content-Type": "application/json" },
		body: JSON.stringify(n)
	}));
	return {
		async status() {
			return f(await fetch(d(e, "webcon_jev_status")));
		},
		async ping() {
			return t("webcon_jev_ping", {});
		},
		async decisions() {
			return f(await fetch(d(e, "webcon_jev_decisions")));
		},
		async save(e) {
			return t("webcon_jev_decision_save", { decision: e });
		},
		async remove(e) {
			return t("webcon_jev_decision_delete", { uid: e });
		},
		async play(e, n) {
			return t("webcon_jev_playground", {
				decision: e,
				context: n
			});
		},
		async runs(t = 0) {
			let n = d(e, "webcon_jev_runs"), r = n.includes("?") ? "&" : "?";
			return f(await fetch(`${n}${r}limit=60&decision=${t}`));
		}
	};
}
//#endregion
//#region Build/Frontend/src/styles.ts
var m = "\n.jev-layout { display: grid; grid-template-columns: minmax(200px, 260px) 1fr; gap: 1rem; align-items: start; }\n@media (max-width: 860px) { .jev-layout { grid-template-columns: 1fr; } }\n\n.jev-stack { display: flex; flex-direction: column; gap: 0.75rem; }\n.jev-row { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }\n.jev-row-end { justify-content: flex-end; }\n.jev-grow { flex: 1 1 auto; min-width: 0; }\n.jev-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.75rem; }\n\n.jev-list { display: flex; flex-direction: column; gap: 0.25rem; }\n.jev-list-item {\n  display: block; width: 100%; text-align: left; cursor: pointer;\n  padding: 0.5rem 0.625rem; border: 1px solid transparent; border-radius: var(--radius-md, 0.5rem);\n  background: transparent; color: inherit; font: inherit; line-height: 1.35;\n}\n.jev-list-item:hover { background: var(--muted); }\n.jev-list-item[aria-current='true'] { background: var(--accent); border-color: var(--border-strong); }\n.jev-list-item small { display: block; color: var(--muted-foreground); font-size: 0.75rem; }\n\n.jev-muted { color: var(--muted-foreground); }\n.jev-small { font-size: 0.8125rem; }\n.jev-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8125rem; }\n\n.jev-panel {\n  border: 1px solid var(--border); border-radius: var(--radius-md, 0.5rem);\n  padding: 0.75rem; background: var(--card);\n}\n.jev-panel + .jev-panel { margin-top: 0.75rem; }\n.jev-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem; }\n\n.jev-bar { height: 0.5rem; border-radius: 999px; background: var(--muted); overflow: hidden; }\n.jev-bar > span { display: block; height: 100%; background: var(--primary); }\n.jev-bar[data-weak='true'] > span { background: var(--destructive); }\n\n.jev-dist { display: grid; grid-template-columns: minmax(80px, 160px) 1fr 3.5rem; gap: 0.5rem; align-items: center; }\n.jev-dist + .jev-dist { margin-top: 0.3rem; }\n\n.jev-scroll { overflow-x: auto; }\n.jev-nowrap { white-space: nowrap; }\n.jev-pre {\n  margin: 0; padding: 0.625rem; border-radius: var(--radius-md, 0.5rem);\n  background: var(--muted); color: var(--foreground);\n  font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem;\n  white-space: pre-wrap; word-break: break-word; max-height: 16rem; overflow: auto;\n}\n.jev-empty { padding: 2rem 1rem; text-align: center; color: var(--muted-foreground); }\n", h = null;
function ee(e) {
	let t = e?.getRootNode();
	if (t instanceof ShadowRoot && typeof CSSStyleSheet < "u") try {
		h === null && (h = new CSSStyleSheet(), h.replaceSync(m)), t.adoptedStyleSheets.includes(h) || (t.adoptedStyleSheets = [...t.adoptedStyleSheets, h]);
	} catch {
		if (t.querySelector("style[data-webcon-jev]") === null) {
			let e = document.createElement("style");
			e.dataset.webconJev = "", e.textContent = m, t.appendChild(e);
		}
	}
}
//#endregion
//#region Build/Frontend/src/editor.tsx
var { Badge: g, Button: _, Card: v, CardContent: y, CardDescription: b, CardHeader: x, CardTitle: S, Input: C, Label: w, Select: T, SelectContent: E, SelectItem: D, SelectTrigger: O, SelectValue: k, Separator: A, Textarea: j } = o, te = {
	choice: "Pick one of several options. Give every option an id and a sentence saying what it means.",
	score: "Place it on an ordered scale. List the levels from lowest to highest; the answer may land between two.",
	noul: "How likely a yes/no statement is true, as a probability. Options are optional — use ids \"yes\" and \"no\"."
}, ne = {
	choice: "What happens when this option wins — for powermail routing, the address that gets the mail.",
	score: "Not used for a score: the position decides, not an outcome value.",
	noul: "What happens on this side of the answer."
};
function re({ draft: e, dirty: t, saving: n, onChange: r, onSave: i, onRevert: a, onDelete: o }) {
	let s = (t, n) => r({
		...e,
		[t]: n
	}), c = (t, n) => {
		let r = [...e.questions];
		r[t] = n, s("questions", r);
	};
	return /* @__PURE__ */ u("div", {
		className: "jev-stack",
		children: [
			/* @__PURE__ */ u(v, { children: [/* @__PURE__ */ u(x, { children: [/* @__PURE__ */ l(S, { children: e.uid > 0 ? e.title || "Untitled decision" : "New decision" }), /* @__PURE__ */ l(b, { children: "A decision is a set of typed questions about one kind of state, plus what to do when the answer is not certain enough to act on." })] }), /* @__PURE__ */ u(y, {
				className: "jev-stack",
				children: [
					/* @__PURE__ */ u("div", {
						className: "jev-fields",
						children: [/* @__PURE__ */ l(M, {
							label: "Title",
							hint: "What an editor calls this.",
							children: /* @__PURE__ */ l(C, {
								value: e.title,
								onChange: (e) => s("title", e.target.value)
							})
						}), /* @__PURE__ */ l(M, {
							label: "Identifier",
							hint: "How code refers to it. Leave empty to derive it from the title.",
							children: /* @__PURE__ */ l(C, {
								value: e.identifier,
								placeholder: "contact_routing",
								onChange: (e) => s("identifier", e.target.value)
							})
						})]
					}),
					/* @__PURE__ */ l(M, {
						label: "What this decides",
						hint: "For whoever reads the run log in six months.",
						children: /* @__PURE__ */ l(j, {
							rows: 2,
							value: e.description,
							onChange: (e) => s("description", e.target.value)
						})
					}),
					/* @__PURE__ */ l(M, {
						label: "State template",
						hint: "What Jev gets to read. Use {{field.marker}} for a form value. Leave empty to send every filled field as JSON.",
						children: /* @__PURE__ */ l(j, {
							rows: 4,
							className: "jev-mono",
							placeholder: "Subject: {{field.subject}}\nMessage: {{field.message}}",
							value: e.stateTemplate,
							onChange: (e) => s("stateTemplate", e.target.value)
						})
					}),
					/* @__PURE__ */ u("div", {
						className: "jev-fields",
						children: [
							/* @__PURE__ */ l(M, {
								label: "Confidence threshold",
								hint: "Below this, the default outcome is used. 0.5 is a coin toss.",
								children: /* @__PURE__ */ l(C, {
									type: "number",
									min: 0,
									max: 1,
									step: .05,
									value: e.confidenceThreshold,
									onChange: (e) => s("confidenceThreshold", Number(e.target.value))
								})
							}),
							/* @__PURE__ */ l(M, {
								label: "Default outcome",
								hint: "Used when Jev cannot answer or is not certain enough.",
								children: /* @__PURE__ */ l(C, {
									value: e.defaultOutcome,
									placeholder: "office@example.com",
									onChange: (e) => s("defaultOutcome", e.target.value)
								})
							}),
							/* @__PURE__ */ l(M, {
								label: "Cache lifetime",
								hint: "Seconds. -1 follows the extension configuration, 0 never caches.",
								children: /* @__PURE__ */ l(C, {
									type: "number",
									min: -1,
									value: e.cacheLifetime,
									onChange: (e) => s("cacheLifetime", Number(e.target.value))
								})
							}),
							/* @__PURE__ */ l(M, {
								label: "Model",
								hint: "Empty uses the configured model.",
								children: /* @__PURE__ */ l(C, {
									value: e.model,
									placeholder: "jev-latest",
									onChange: (e) => s("model", e.target.value)
								})
							})
						]
					})
				]
			})] }),
			/* @__PURE__ */ u(v, { children: [/* @__PURE__ */ u(x, { children: [/* @__PURE__ */ l(S, { children: "Questions" }), /* @__PURE__ */ l(b, { children: "All of them go over in one request and are answered in one pass, so asking four costs barely more than asking one." })] }), /* @__PURE__ */ u(y, {
				className: "jev-stack",
				children: [
					e.questions.length === 0 ? /* @__PURE__ */ l("p", {
						className: "jev-empty",
						children: "No questions yet. A decision without one never calls Jev."
					}) : null,
					e.questions.map((t, n) => /* @__PURE__ */ l(ie, {
						question: t,
						onChange: (e) => c(n, e),
						onRemove: () => s("questions", e.questions.filter((e, t) => t !== n))
					}, t.uid > 0 ? `q${t.uid}` : `new${n}`)),
					/* @__PURE__ */ l("div", { children: /* @__PURE__ */ l(_, {
						variant: "outline",
						size: "sm",
						onClick: () => s("questions", [...e.questions, {
							uid: 0,
							name: "",
							type: "choice",
							instructions: "",
							criteria: []
						}]),
						children: "Add question"
					}) })
				]
			})] }),
			/* @__PURE__ */ u("div", {
				className: "jev-row jev-row-end",
				children: [
					e.uid > 0 ? /* @__PURE__ */ l(_, {
						variant: "ghost",
						onClick: o,
						children: "Delete"
					}) : null,
					/* @__PURE__ */ l(_, {
						variant: "outline",
						onClick: a,
						disabled: !t || n,
						children: "Revert"
					}),
					/* @__PURE__ */ l(_, {
						onClick: i,
						disabled: !t || n,
						children: n ? "Saving…" : "Save decision"
					})
				]
			})
		]
	});
}
function ie({ question: e, onChange: t, onRemove: n }) {
	let r = (n, r) => t({
		...e,
		[n]: r
	}), i = (t, n) => {
		let i = [...e.criteria];
		i[t] = n, r("criteria", i);
	};
	return /* @__PURE__ */ u("div", {
		className: "jev-panel",
		children: [/* @__PURE__ */ u("div", {
			className: "jev-panel-head",
			children: [/* @__PURE__ */ u("span", {
				className: "jev-row",
				children: [/* @__PURE__ */ l("strong", {
					className: "jev-mono",
					children: e.name || "unnamed"
				}), /* @__PURE__ */ l(g, {
					variant: "secondary",
					children: e.type
				})]
			}), /* @__PURE__ */ l(_, {
				variant: "ghost",
				size: "sm",
				onClick: n,
				children: "Remove"
			})]
		}), /* @__PURE__ */ u("div", {
			className: "jev-stack",
			children: [
				/* @__PURE__ */ u("div", {
					className: "jev-fields",
					children: [/* @__PURE__ */ l(M, {
						label: "Name",
						hint: "The key the answer comes back under. Conditions refer to it.",
						children: /* @__PURE__ */ l(C, {
							value: e.name,
							placeholder: "department",
							onChange: (e) => r("name", e.target.value)
						})
					}), /* @__PURE__ */ l(M, {
						label: "Type",
						hint: te[e.type],
						children: /* @__PURE__ */ u(T, {
							value: e.type,
							onValueChange: (e) => r("type", e),
							children: [/* @__PURE__ */ l(O, { children: /* @__PURE__ */ l(k, {}) }), /* @__PURE__ */ u(E, { children: [
								/* @__PURE__ */ l(D, {
									value: "choice",
									children: "Choice"
								}),
								/* @__PURE__ */ l(D, {
									value: "score",
									children: "Score"
								}),
								/* @__PURE__ */ l(D, {
									value: "noul",
									children: "Noul"
								})
							] })]
						})
					})]
				}),
				/* @__PURE__ */ l(M, {
					label: "Question",
					hint: "Put it as you would to a colleague who can only see the state — no background, no example answers.",
					children: /* @__PURE__ */ l(j, {
						rows: 2,
						value: e.instructions,
						placeholder: "Which department should answer this enquiry?",
						onChange: (e) => r("instructions", e.target.value)
					})
				}),
				/* @__PURE__ */ l(A, {}),
				/* @__PURE__ */ u("div", {
					className: "jev-panel-head",
					children: [/* @__PURE__ */ l("span", {
						className: "jev-small jev-muted",
						children: e.type === "score" ? "Levels, lowest first" : "Options"
					}), /* @__PURE__ */ u(_, {
						variant: "outline",
						size: "sm",
						onClick: () => r("criteria", [...e.criteria, {
							uid: 0,
							identifier: "",
							description: "",
							outcomeValue: ""
						}]),
						children: ["Add ", e.type === "score" ? "level" : "option"]
					})]
				}),
				e.criteria.map((t, n) => /* @__PURE__ */ u("div", {
					className: "jev-fields",
					children: [
						e.type === "score" ? null : /* @__PURE__ */ l(M, {
							label: "Id",
							hint: "How this option is named in the answer.",
							children: /* @__PURE__ */ l(C, {
								value: t.identifier,
								placeholder: e.type === "noul" ? "yes" : "sales",
								onChange: (e) => i(n, {
									...t,
									identifier: e.target.value
								})
							})
						}),
						/* @__PURE__ */ l(M, {
							label: "Meaning",
							hint: "What the model reads to tell the options apart.",
							children: /* @__PURE__ */ l(C, {
								value: t.description,
								onChange: (e) => i(n, {
									...t,
									description: e.target.value
								})
							})
						}),
						e.type === "score" ? null : /* @__PURE__ */ l(M, {
							label: "Outcome",
							hint: ne[e.type],
							children: /* @__PURE__ */ l(C, {
								value: t.outcomeValue,
								placeholder: "sales@example.com",
								onChange: (e) => i(n, {
									...t,
									outcomeValue: e.target.value
								})
							})
						}),
						/* @__PURE__ */ l("div", {
							style: {
								display: "flex",
								alignItems: "flex-end"
							},
							children: /* @__PURE__ */ l(_, {
								variant: "ghost",
								size: "sm",
								onClick: () => r("criteria", e.criteria.filter((e, t) => t !== n)),
								children: "Remove"
							})
						})
					]
				}, t.uid > 0 ? `c${t.uid}` : `newc${n}`))
			]
		})]
	});
}
function M({ label: e, hint: t, children: n }) {
	return /* @__PURE__ */ u("div", {
		className: "jev-stack",
		style: { gap: "0.3rem" },
		children: [
			/* @__PURE__ */ l(w, { children: e }),
			n,
			t ? /* @__PURE__ */ l("span", {
				className: "jev-small jev-muted",
				children: t
			}) : null
		]
	});
}
//#endregion
//#region Build/Frontend/src/format.ts
function N(e) {
	return e === 0 ? "$0" : e < .01 ? `$${e.toFixed(6)}` : `$${e.toFixed(4)}`;
}
function P(e) {
	return e >= 1 ? `${Math.round(e)} ms` : "—";
}
function ae(e) {
	return (/* @__PURE__ */ new Date(e * 1e3)).toLocaleString();
}
function oe(e) {
	return e.value === null ? "—" : typeof e.value == "number" ? e.value.toFixed(2) : e.value;
}
function se(e) {
	let t = e.probabilities;
	return Array.isArray(t) ? t.map((t, n) => ({
		label: e.legend?.[n] ?? `Level ${n}`,
		value: t
	})) : t && typeof t == "object" ? Object.entries(t).map(([e, t]) => ({
		label: e,
		value: t
	})) : e.type === "noul" && typeof e.value == "number" ? [{
		label: "yes",
		value: e.value
	}, {
		label: "no",
		value: 1 - e.value
	}] : [];
}
function ce(e) {
	return {
		uid: 0,
		identifier: "",
		title: "",
		description: "",
		stateTemplate: "",
		model: "",
		confidenceThreshold: e,
		cacheLifetime: -1,
		defaultOutcome: "",
		languageId: 0,
		questions: []
	};
}
//#endregion
//#region Build/Frontend/src/bars.tsx
var { Badge: F } = o;
function le({ answer: e, threshold: t }) {
	let n = e.confidence < t, r = se(e);
	return /* @__PURE__ */ u("div", {
		className: "jev-panel",
		children: [
			/* @__PURE__ */ u("div", {
				className: "jev-panel-head",
				children: [/* @__PURE__ */ l("strong", {
					className: "jev-mono",
					children: e.name
				}), /* @__PURE__ */ u("span", {
					className: "jev-row",
					children: [/* @__PURE__ */ l(F, {
						variant: "secondary",
						children: e.type
					}), /* @__PURE__ */ u(F, {
						variant: n ? "destructive" : "default",
						children: [
							oe(e),
							" · ",
							(e.confidence * 100).toFixed(0),
							"%"
						]
					})]
				})]
			}),
			n ? /* @__PURE__ */ u("p", {
				className: "jev-small jev-muted",
				style: { margin: "0 0 0.5rem" },
				children: [
					"Below the decision's threshold of ",
					(t * 100).toFixed(0),
					"% — the default outcome would be used and the submission flagged for a human."
				]
			}) : null,
			r.map((e) => /* @__PURE__ */ u("div", {
				className: "jev-dist",
				children: [
					/* @__PURE__ */ l("span", {
						className: "jev-small jev-nowrap",
						title: e.label,
						children: e.label
					}),
					/* @__PURE__ */ l("span", {
						className: "jev-bar",
						"data-weak": n ? "true" : "false",
						children: /* @__PURE__ */ l("span", { style: { width: `${Math.max(0, Math.min(1, e.value)) * 100}%` } })
					}),
					/* @__PURE__ */ u("span", {
						className: "jev-small jev-muted jev-nowrap",
						children: [(e.value * 100).toFixed(1), "%"]
					})
				]
			}, e.label))
		]
	});
}
//#endregion
//#region Build/Frontend/src/playground.tsx
var { Alert: ue, AlertDescription: de, AlertTitle: fe, Badge: pe, Button: me, Card: I, CardContent: L, CardDescription: R, CardHeader: z, CardTitle: B, Label: he, Textarea: ge } = o;
function _e({ api: e, decision: t }) {
	let [n, r] = i(""), [a, o] = i(!1), [s, d] = i(null), f = async () => {
		o(!0);
		try {
			d(await e.play(t.uid, { field: { message: n } }));
		} catch (e) {
			d({
				ok: !1,
				error: e instanceof Error ? e.message : String(e)
			});
		} finally {
			o(!1);
		}
	}, p = s?.result;
	return /* @__PURE__ */ u("div", {
		className: "jev-stack",
		children: [/* @__PURE__ */ u(I, { children: [/* @__PURE__ */ u(z, { children: [/* @__PURE__ */ l(B, { children: "Try it" }), /* @__PURE__ */ u(R, { children: [
			"What you type arrives as ",
			/* @__PURE__ */ l("code", {
				className: "jev-mono",
				children: "field.message"
			}),
			". With no state template, that is the whole state; with one, only what the template reads is sent."
		] })] }), /* @__PURE__ */ u(L, {
			className: "jev-stack",
			children: [
				/* @__PURE__ */ l(ge, {
					rows: 6,
					value: n,
					placeholder: "Our invoice 4711 was charged twice and nobody has answered my mail for a week.",
					onChange: (e) => r(e.target.value)
				}),
				/* @__PURE__ */ l("div", {
					className: "jev-row jev-row-end",
					children: /* @__PURE__ */ l(me, {
						onClick: f,
						disabled: a || t.uid === 0 || n.trim() === "",
						children: a ? "Asking Jev…" : "Run"
					})
				}),
				t.uid === 0 ? /* @__PURE__ */ l("p", {
					className: "jev-small jev-muted",
					children: "Save the decision first — the playground runs the stored one."
				}) : null
			]
		})] }), s === null ? null : /* @__PURE__ */ u(I, { children: [/* @__PURE__ */ u(z, { children: [/* @__PURE__ */ l(B, { children: "Result" }), p ? /* @__PURE__ */ u(R, { children: [
			P(p.durationMs),
			" · ",
			p.usage.inputTokens,
			" input tokens ·",
			" ",
			N(p.usage.costUsd),
			" · model ",
			p.model || "—"
		] }) : null] }), /* @__PURE__ */ u(L, {
			className: "jev-stack",
			children: [
				s.error || p?.isFallback ? /* @__PURE__ */ u(ue, {
					variant: "destructive",
					children: [/* @__PURE__ */ l(fe, { children: "No answer — the default would be used" }), /* @__PURE__ */ l(de, { children: s.error ?? p?.fallbackReason })]
				}) : null,
				p && Object.values(p.answers).length > 0 ? /* @__PURE__ */ u(c, { children: [Object.values(p.answers).map((e) => /* @__PURE__ */ l(le, {
					answer: e,
					threshold: t.confidenceThreshold
				}, e.name)), s.outcomes && Object.keys(s.outcomes).length > 0 ? /* @__PURE__ */ u("div", {
					className: "jev-panel",
					children: [/* @__PURE__ */ u("div", {
						className: "jev-panel-head",
						children: [/* @__PURE__ */ l("strong", { children: "Where this would go" }), s.needsHumanReview ? /* @__PURE__ */ l(pe, {
							variant: "destructive",
							children: "needs review"
						}) : null]
					}), Object.entries(s.outcomes).map(([e, t]) => /* @__PURE__ */ u("div", {
						className: "jev-row",
						children: [
							/* @__PURE__ */ l("span", {
								className: "jev-mono jev-small",
								children: e
							}),
							/* @__PURE__ */ l("span", {
								className: "jev-muted",
								children: "→"
							}),
							/* @__PURE__ */ l("span", {
								className: "jev-mono jev-small",
								children: t
							})
						]
					}, e))]
				}) : null] }) : null,
				/* @__PURE__ */ u("div", { children: [/* @__PURE__ */ l(he, { children: "State as sent" }), /* @__PURE__ */ l("pre", {
					className: "jev-pre",
					children: JSON.stringify(s.state ?? null, null, 2)
				})] })
			]
		})] })]
	});
}
//#endregion
//#region Build/Frontend/src/runlog.tsx
var { Badge: V, Button: ve, Card: ye, CardContent: be, CardDescription: xe, CardHeader: Se, CardTitle: Ce, Table: we, TableBody: Te, TableCell: H, TableHead: U, TableHeader: Ee, TableRow: W } = o, De = {
	powermail_cond: "condition",
	powermail_finisher: "routing",
	playground: "playground",
	cli: "CLI"
};
function Oe({ api: e, decisionUid: n }) {
	let [r, a] = i([]), [o, s] = i(!0), c = async () => {
		s(!0);
		try {
			let t = await e.runs(n);
			a(t.runs);
		} finally {
			s(!1);
		}
	};
	return t(() => {
		c();
	}, [n]), /* @__PURE__ */ u(ye, { children: [/* @__PURE__ */ u(Se, { children: [/* @__PURE__ */ l(Ce, { children: "Runs" }), /* @__PURE__ */ l(xe, { children: "The most recent calls for this decision. “cached” never reached the API and cost nothing; “fallback” means the default outcome was used." })] }), /* @__PURE__ */ u(be, {
		className: "jev-stack",
		children: [/* @__PURE__ */ l("div", {
			className: "jev-row jev-row-end",
			children: /* @__PURE__ */ l(ve, {
				variant: "outline",
				size: "sm",
				onClick: () => void c(),
				disabled: o,
				children: o ? "Loading…" : "Refresh"
			})
		}), r.length === 0 ? /* @__PURE__ */ l("p", {
			className: "jev-empty",
			children: o ? "Loading…" : "Nothing yet. Run the decision in the playground or submit a form."
		}) : /* @__PURE__ */ l("div", {
			className: "jev-scroll",
			children: /* @__PURE__ */ u(we, { children: [/* @__PURE__ */ l(Ee, { children: /* @__PURE__ */ u(W, { children: [
				/* @__PURE__ */ l(U, { children: "When" }),
				/* @__PURE__ */ l(U, { children: "Where from" }),
				/* @__PURE__ */ l(U, { children: "Answers" }),
				/* @__PURE__ */ l(U, {
					className: "jev-nowrap",
					children: "Latency"
				}),
				/* @__PURE__ */ l(U, {
					className: "jev-nowrap",
					children: "Cost"
				})
			] }) }), /* @__PURE__ */ l(Te, { children: r.map((e) => /* @__PURE__ */ u(W, { children: [
				/* @__PURE__ */ l(H, {
					className: "jev-nowrap jev-small",
					children: ae(e.crdate)
				}),
				/* @__PURE__ */ u(H, {
					className: "jev-small",
					children: [/* @__PURE__ */ u("div", {
						className: "jev-row",
						children: [
							/* @__PURE__ */ l(V, {
								variant: "secondary",
								children: De[e.context] ?? e.context
							}),
							e.fromCache ? /* @__PURE__ */ l(V, {
								variant: "outline",
								children: "cached"
							}) : null,
							e.isFallback ? /* @__PURE__ */ l(V, {
								variant: "destructive",
								children: "fallback"
							}) : null
						]
					}), /* @__PURE__ */ l("span", {
						className: "jev-muted",
						children: e.origin
					})]
				}),
				/* @__PURE__ */ l(H, {
					className: "jev-small",
					children: e.isFallback ? /* @__PURE__ */ l("span", {
						className: "jev-muted",
						children: e.fallbackReason || "—"
					}) : Object.values(e.answers).map((e) => /* @__PURE__ */ u("div", {
						className: "jev-mono",
						children: [
							e.name,
							"=",
							oe(e),
							" ",
							/* @__PURE__ */ u("span", {
								className: "jev-muted",
								children: [(e.confidence * 100).toFixed(0), "%"]
							})
						]
					}, e.name))
				}),
				/* @__PURE__ */ l(H, {
					className: "jev-nowrap jev-small",
					children: P(e.durationMs)
				}),
				/* @__PURE__ */ l(H, {
					className: "jev-nowrap jev-small",
					children: N(e.costUsd)
				})
			] }, e.uid)) })] })
		})]
	})] });
}
//#endregion
//#region Build/Frontend/src/connection.tsx
var { Alert: G, AlertDescription: K, AlertTitle: q, Badge: J, Button: ke, Card: Ae, CardContent: je, CardDescription: Me, CardHeader: Ne, CardTitle: Pe, Separator: Y } = o;
function Fe({ api: e, initial: n }) {
	let [r, a] = i(n), [o, s] = i(null), [c, d] = i(null), [f, p] = i(!1), [m, h] = i(null);
	return t(() => {
		(async () => {
			let t = await e.status();
			a(t.connection), s(t.today), d(t.month);
		})();
	}, []), /* @__PURE__ */ u("div", {
		className: "jev-stack",
		children: [/* @__PURE__ */ u(Ae, { children: [/* @__PURE__ */ u(Ne, { children: [/* @__PURE__ */ l(Pe, { children: "Connection" }), /* @__PURE__ */ l(Me, { children: "The token is read from nr-vault, falling back to the TYPESAFE_API_KEY environment variable. It never leaves the server — only the decision does." })] }), /* @__PURE__ */ u(je, {
			className: "jev-stack",
			children: [
				/* @__PURE__ */ l(X, {
					label: "Endpoint",
					value: r.endpoint,
					mono: !0
				}),
				/* @__PURE__ */ l(X, {
					label: "Model",
					value: r.model,
					mono: !0
				}),
				/* @__PURE__ */ l(X, {
					label: "Token",
					value: r.tokenSource
				}),
				/* @__PURE__ */ l(X, {
					label: "Decisions enabled",
					value: r.enabled ? "yes" : "no — everything falls back"
				}),
				r.maxCallsPerMinute === void 0 ? null : /* @__PURE__ */ l(X, {
					label: "Budget guard",
					value: r.maxCallsPerMinute === 0 ? "off" : `${r.maxCallsPerMinute} calls a minute`
				}),
				r.hasToken ? null : /* @__PURE__ */ u(G, {
					variant: "destructive",
					children: [/* @__PURE__ */ l(q, { children: "No API token" }), /* @__PURE__ */ u(K, { children: [
						"Set TYPESAFE_API_KEY in .ddev/config.local.yaml, restart ddev, then run",
						" ",
						/* @__PURE__ */ l("code", {
							className: "jev-mono",
							children: "vendor/bin/typo3 webcon-jev:token:import"
						}),
						"."
					] })]
				}),
				/* @__PURE__ */ l(Y, {}),
				/* @__PURE__ */ l("div", {
					className: "jev-row jev-row-end",
					children: /* @__PURE__ */ l(ke, {
						variant: "outline",
						onClick: async () => {
							p(!0);
							try {
								h(await e.ping());
							} catch (e) {
								h({
									ok: !1,
									error: e instanceof Error ? e.message : String(e)
								});
							} finally {
								p(!1);
							}
						},
						disabled: f || !r.hasToken,
						children: f ? "Asking…" : "Send a test question"
					})
				}),
				m === null ? null : m.ok ? /* @__PURE__ */ u(G, { children: [/* @__PURE__ */ l(q, { children: "Jev answered" }), /* @__PURE__ */ l(K, { children: "The token, the endpoint and the network all work." })] }) : /* @__PURE__ */ u(G, {
					variant: "destructive",
					children: [/* @__PURE__ */ l(q, { children: "No answer" }), /* @__PURE__ */ l(K, { children: m.error })]
				})
			]
		})] }), /* @__PURE__ */ u(Ae, { children: [/* @__PURE__ */ u(Ne, { children: [/* @__PURE__ */ l(Pe, { children: "What it has cost" }), /* @__PURE__ */ l(Me, { children: "Jev bills input tokens only, at $0.042 per million. Output is free." })] }), /* @__PURE__ */ u(je, {
			className: "jev-stack",
			children: [
				/* @__PURE__ */ l(Ie, {
					label: "Last 24 hours",
					totals: o
				}),
				/* @__PURE__ */ l(Y, {}),
				/* @__PURE__ */ l(Ie, {
					label: "Last 30 days",
					totals: c
				})
			]
		})] })]
	});
}
function X({ label: e, value: t, mono: n = !1 }) {
	return /* @__PURE__ */ u("div", {
		className: "jev-row",
		children: [/* @__PURE__ */ l("span", {
			className: "jev-small jev-muted",
			style: { minWidth: "9rem" },
			children: e
		}), /* @__PURE__ */ l("span", {
			className: n ? "jev-mono jev-grow" : "jev-grow",
			children: t
		})]
	});
}
function Ie({ label: e, totals: t }) {
	return t === null ? /* @__PURE__ */ u("p", {
		className: "jev-small jev-muted",
		children: [e, ": loading…"]
	}) : /* @__PURE__ */ u("div", {
		className: "jev-stack",
		style: { gap: "0.35rem" },
		children: [/* @__PURE__ */ l("strong", {
			className: "jev-small",
			children: e
		}), /* @__PURE__ */ u("div", {
			className: "jev-row",
			children: [
				/* @__PURE__ */ u(J, {
					variant: "secondary",
					children: [t.runs, " runs"]
				}),
				/* @__PURE__ */ u(J, {
					variant: "outline",
					children: [t.calls, " reached the API"]
				}),
				t.fallbacks > 0 ? /* @__PURE__ */ u(J, {
					variant: "destructive",
					children: [t.fallbacks, " fell back"]
				}) : null,
				/* @__PURE__ */ u(J, {
					variant: "outline",
					children: [t.inputTokens.toLocaleString(), " tokens"]
				}),
				/* @__PURE__ */ l(J, { children: N(t.costUsd) }),
				/* @__PURE__ */ u("span", {
					className: "jev-small jev-muted",
					children: ["average ", P(t.avgDurationMs)]
				})
			]
		})]
	});
}
//#endregion
//#region Build/Frontend/src/main.tsx
var { Badge: Le, Button: Re, Tabs: ze, TabsContent: Z, TabsList: Be, TabsTrigger: Q, Toaster: Ve, toast: $ } = o;
function He({ props: a, shell: o }) {
	let { ajaxUrls: c } = s(), d = n(() => p(c), [c]), f = r(null), m = a.defaults ?? {}, h = a.connection ?? {}, [g, _] = i(() => Array.isArray(a.decisions) ? a.decisions : []), [v, y] = i(() => Array.isArray(a.decisions) && a.decisions.length > 0 ? a.decisions[0].uid : 0), [b, x] = i(null), [S, C] = i(!1), [w, T] = i("editor");
	t(() => ee(f.current), []);
	let E = n(() => g.find((e) => e.uid === v) ?? null, [g, v]);
	t(() => {
		x(E === null ? null : structuredClone(E));
	}, [E]), t(() => {
		o.setContext({
			view: "jev-decisions",
			tab: w,
			decision: b?.identifier ?? null,
			questions: b?.questions.map((e) => e.name) ?? []
		});
	}, [
		o,
		w,
		b
	]);
	let D = n(() => b !== null && JSON.stringify(b) !== JSON.stringify(E ?? ce(.6)), [b, E]), O = e(() => {
		y(0), x(ce(m.confidenceThreshold ?? .6)), T("editor");
	}, [m.confidenceThreshold]), k = e(async () => {
		let e = await d.decisions();
		return _(e.decisions), e.decisions;
	}, [d]), A = e(async () => {
		if (b !== null) {
			C(!0);
			try {
				let e = await d.save(b);
				if (!e.ok) {
					$.error("The decision was not saved", { description: e.error ?? "The DataHandler refused the record. Check the TYPO3 log." });
					return;
				}
				let t = e.decision;
				await k(), t && y(t.uid), $.success("Decision saved");
			} catch (e) {
				$.error("The decision was not saved", { description: e instanceof Error ? e.message : String(e) });
			} finally {
				C(!1);
			}
		}
	}, [
		d,
		b,
		k
	]), j = e(async () => {
		if (b === null || b.uid === 0) return;
		let e = await d.remove(b.uid);
		if (!e.ok) {
			$.error("The decision was not deleted", { description: e.error });
			return;
		}
		let t = await k();
		y(t[0]?.uid ?? 0), $.success("Decision deleted");
	}, [
		d,
		b,
		k
	]);
	return /* @__PURE__ */ u("div", {
		ref: f,
		className: "jev-layout",
		children: [
			/* @__PURE__ */ l(Ve, {}),
			/* @__PURE__ */ u("nav", {
				className: "jev-stack",
				children: [
					/* @__PURE__ */ u("div", {
						className: "jev-row",
						style: { justifyContent: "space-between" },
						children: [/* @__PURE__ */ l("strong", {
							className: "jev-small",
							children: "Decisions"
						}), /* @__PURE__ */ l(Le, {
							variant: "secondary",
							children: g.length
						})]
					}),
					/* @__PURE__ */ u("div", {
						className: "jev-list",
						children: [g.map((e) => /* @__PURE__ */ u("button", {
							type: "button",
							className: "jev-list-item",
							"aria-current": e.uid === v,
							onClick: () => y(e.uid),
							children: [e.title || "Untitled", /* @__PURE__ */ l("small", {
								className: "jev-mono",
								children: e.identifier || "—"
							})]
						}, e.uid)), g.length === 0 ? /* @__PURE__ */ l("p", {
							className: "jev-small jev-muted",
							children: "None yet."
						}) : null]
					}),
					/* @__PURE__ */ l(Re, {
						variant: "outline",
						size: "sm",
						onClick: O,
						children: "New decision"
					})
				]
			}),
			/* @__PURE__ */ l("main", { children: /* @__PURE__ */ u(ze, {
				value: w,
				onValueChange: T,
				children: [
					/* @__PURE__ */ u(Be, { children: [
						/* @__PURE__ */ l(Q, {
							value: "editor",
							children: "Editor"
						}),
						/* @__PURE__ */ l(Q, {
							value: "playground",
							children: "Playground"
						}),
						/* @__PURE__ */ l(Q, {
							value: "runs",
							children: "Runs"
						}),
						/* @__PURE__ */ l(Q, {
							value: "connection",
							children: "Connection"
						})
					] }),
					/* @__PURE__ */ l(Z, {
						value: "editor",
						children: b === null ? /* @__PURE__ */ l("p", {
							className: "jev-empty",
							children: "Pick a decision on the left, or make a new one."
						}) : /* @__PURE__ */ l(re, {
							draft: b,
							dirty: D,
							saving: S,
							onChange: x,
							onSave: () => void A(),
							onRevert: () => x(E === null ? null : structuredClone(E)),
							onDelete: () => void j()
						})
					}),
					/* @__PURE__ */ l(Z, {
						value: "playground",
						children: b === null ? /* @__PURE__ */ l("p", {
							className: "jev-empty",
							children: "Pick a decision to try it."
						}) : /* @__PURE__ */ l(_e, {
							api: d,
							decision: b
						})
					}),
					/* @__PURE__ */ l(Z, {
						value: "runs",
						children: /* @__PURE__ */ l(Oe, {
							api: d,
							decisionUid: v
						})
					}),
					/* @__PURE__ */ l(Z, {
						value: "connection",
						children: /* @__PURE__ */ l(Fe, {
							api: d,
							initial: h
						})
					})
				]
			}) })
		]
	});
}
a("webcon_jev/decisions", He);
//#endregion
